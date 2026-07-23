<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\PasswordReset;

use App\Application\PasswordReset\RequestPasswordResetCommand;
use App\Application\PasswordReset\RequestPasswordResetHandler;
use App\Domain\PasswordReset\PasswordResetToken;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryPasswordResetTokenRepository;
use App\Tests\Fake\InMemoryUserRepository;
use App\Tests\Fake\SpyAuditLogger;
use App\Tests\Fake\SpyPasswordResetMailer;
use PHPUnit\Framework\TestCase;

final class RequestPasswordResetHandlerTest extends TestCase
{
    public function testUnknownEmailDoesNotCreateTokenNorSendMail(): void
    {
        $tokens = new InMemoryPasswordResetTokenRepository();
        $mailer = new SpyPasswordResetMailer();
        $handler = new RequestPasswordResetHandler(new InMemoryUserRepository(), $tokens, $mailer, new SpyAuditLogger());

        $handler(new RequestPasswordResetCommand('ghost@example.com'));

        self::assertSame([], $tokens->tokens);
        self::assertSame([], $mailer->sent);
    }

    public function testKnownEmailReplacesOldTokensAndSendsMail(): void
    {
        $user = User::register('user@example.com', 'hash');
        $users = (new InMemoryUserRepository())->withUser(1, $user);

        $tokens = new InMemoryPasswordResetTokenRepository();
        $tokens->save(PasswordResetToken::issueFor($user, 'old-token', new \DateTimeImmutable()));

        $mailer = new SpyPasswordResetMailer();
        $handler = new RequestPasswordResetHandler($users, $tokens, $mailer, new SpyAuditLogger());

        $handler(new RequestPasswordResetCommand('user@example.com'));

        self::assertCount(1, $tokens->tokens);
        self::assertCount(1, $mailer->sent);
        self::assertSame(1, $tokens->deleteForUserCalls);
        self::assertSame(1, $tokens->deleteExpiredCalls);

        // Письмо содержит сырой токен, а в хранилище лежит только его hash
        $rawToken = $mailer->sent[0]['rawToken'];
        self::assertSame(PasswordResetToken::hashOf($rawToken), $tokens->tokens[0]->getTokenHash());
    }
}
