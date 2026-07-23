<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\PasswordReset;

use App\Application\PasswordReset\ResetPasswordCommand;
use App\Application\PasswordReset\ResetPasswordHandler;
use App\Domain\PasswordReset\Exception\InvalidResetTokenException;
use App\Domain\PasswordReset\PasswordResetToken;
use App\Domain\User\User;
use App\Tests\Fake\FakePasswordHasherFactory;
use App\Tests\Fake\FakeTransactionRunner;
use App\Tests\Fake\InMemoryPasswordResetTokenRepository;
use App\Tests\Fake\InMemoryUserRepository;
use App\Tests\Fake\SpyAuditLogger;
use PHPUnit\Framework\TestCase;

final class ResetPasswordHandlerTest extends TestCase
{
    public function testValidTokenChangesPasswordAndDestroysToken(): void
    {
        $user = User::register('user@example.com', 'hashed:old');
        $tokens = new InMemoryPasswordResetTokenRepository();
        $tokens->save(PasswordResetToken::issueFor($user, 'raw-token', new \DateTimeImmutable()));

        $users = new InMemoryUserRepository();
        $handler = new ResetPasswordHandler($tokens, $users, new FakePasswordHasherFactory(), new FakeTransactionRunner(), new SpyAuditLogger());

        $handler(new ResetPasswordCommand('raw-token', 'newpassword1', 'newpassword1'));

        self::assertSame('hashed:newpassword1', $user->getPassword());
        self::assertSame([], $tokens->tokens, 'Токен должен быть одноразовым');
        self::assertCount(1, $users->saved);
    }

    public function testPasswordChangeAndTokenRemovalRunInOneTransaction(): void
    {
        $user = User::register('user@example.com', 'hashed:old');
        $tokens = new InMemoryPasswordResetTokenRepository();
        $tokens->save(PasswordResetToken::issueFor($user, 'raw-token', new \DateTimeImmutable()));

        $transactions = new FakeTransactionRunner();
        $handler = new ResetPasswordHandler(
            $tokens,
            new InMemoryUserRepository(),
            new FakePasswordHasherFactory(),
            $transactions,
            new SpyAuditLogger(),
        );

        $handler(new ResetPasswordCommand('raw-token', 'newpassword1', 'newpassword1'));

        self::assertSame(1, $transactions->transactions, 'Смена пароля и удаление токена — одна транзакция');
    }

    public function testUnknownTokenIsRejected(): void
    {
        $handler = new ResetPasswordHandler(
            new InMemoryPasswordResetTokenRepository(),
            new InMemoryUserRepository(),
            new FakePasswordHasherFactory(),
            new FakeTransactionRunner(),
            new SpyAuditLogger(),
        );

        $this->expectException(InvalidResetTokenException::class);

        $handler(new ResetPasswordCommand('missing-token', 'newpassword1', 'newpassword1'));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $user = User::register('user@example.com', 'hash');
        $tokens = new InMemoryPasswordResetTokenRepository();
        $tokens->save(PasswordResetToken::issueFor($user, 'raw-token', new \DateTimeImmutable('-2 hours')));

        $handler = new ResetPasswordHandler(
            $tokens,
            new InMemoryUserRepository(),
            new FakePasswordHasherFactory(),
            new FakeTransactionRunner(),
            new SpyAuditLogger(),
        );

        $this->expectException(InvalidResetTokenException::class);

        $handler(new ResetPasswordCommand('raw-token', 'newpassword1', 'newpassword1'));
    }
}
