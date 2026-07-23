<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PasswordReset;

use App\Domain\PasswordReset\PasswordResetToken;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    public function testIssueForStoresSha256HashInsteadOfRawToken(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $token = PasswordResetToken::issueFor(self::user(), 'raw-token', $now);

        self::assertSame(hash('sha256', 'raw-token'), $token->getTokenHash());
        self::assertStringNotContainsString('raw-token', $token->getTokenHash());
    }

    public function testTokenLivesExactlyOneHour(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $token = PasswordResetToken::issueFor(self::user(), 'raw-token', $now);

        self::assertFalse($token->isExpiredAt($now));
        self::assertFalse($token->isExpiredAt($now->modify('+59 minutes')));
        self::assertTrue($token->isExpiredAt($now->modify('+1 hour')));
    }

    public function testTokenHashColumnIsDeclaredUnique(): void
    {
        $attributes = new \ReflectionClass(PasswordResetToken::class)
            ->getAttributes(\Doctrine\ORM\Mapping\UniqueConstraint::class);

        self::assertNotEmpty($attributes, 'token_hash должен быть под уникальным индексом');
        self::assertSame(['token_hash'], $attributes[0]->newInstance()->columns);
    }

    private static function user(): User
    {
        return User::register('user@example.com', 'hash');
    }
}
