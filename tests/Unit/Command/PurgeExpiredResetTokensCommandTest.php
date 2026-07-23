<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\PurgeExpiredResetTokensCommand;
use App\Domain\PasswordReset\PasswordResetToken;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryPasswordResetTokenRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeExpiredResetTokensCommandTest extends TestCase
{
    public function testRemovesOnlyExpiredTokens(): void
    {
        $user = User::register('user@example.com', 'hash');
        $tokens = new InMemoryPasswordResetTokenRepository();
        $tokens->save(PasswordResetToken::issueFor($user, 'expired', new \DateTimeImmutable('-2 hours')));
        $tokens->save(PasswordResetToken::issueFor($user, 'active', new \DateTimeImmutable()));

        $tester = new CommandTester(new PurgeExpiredResetTokensCommand($tokens));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertCount(1, $tokens->tokens, 'Живой токен должен остаться');
        self::assertStringContainsString('1', $tester->getDisplay(), 'В выводе — число удалённых токенов');
    }

    public function testReportsZeroWhenNothingExpired(): void
    {
        $tester = new CommandTester(new PurgeExpiredResetTokensCommand(new InMemoryPasswordResetTokenRepository()));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('0', $tester->getDisplay());
    }
}
