<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\PasswordReset\PasswordResetToken;
use App\Domain\PasswordReset\PasswordResetTokenRepositoryInterface;
use App\Domain\User\User;

final class InMemoryPasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    /**
     * @var list<PasswordResetToken>
     */
    public array $tokens = [];

    public int $deleteForUserCalls = 0;

    public int $deleteExpiredCalls = 0;

    public function findValidByHash(string $tokenHash, \DateTimeImmutable $now): ?PasswordResetToken
    {
        foreach ($this->tokens as $token) {
            if ($token->getTokenHash() === $tokenHash && !$token->isExpiredAt($now)) {
                return $token;
            }
        }

        return null;
    }

    public function deleteForUser(User $user): void
    {
        ++$this->deleteForUserCalls;
        $this->tokens = array_values(
            array_filter($this->tokens, static fn (PasswordResetToken $token): bool => $token->getUser() !== $user),
        );
    }

    public function deleteExpired(\DateTimeImmutable $now): int
    {
        ++$this->deleteExpiredCalls;
        $before = \count($this->tokens);
        $this->tokens = array_values(
            array_filter($this->tokens, static fn (PasswordResetToken $token): bool => !$token->isExpiredAt($now)),
        );

        return $before - \count($this->tokens);
    }

    public function save(PasswordResetToken $token): void
    {
        $this->tokens[] = $token;
    }

    public function remove(PasswordResetToken $token): void
    {
        $this->tokens = array_values(
            array_filter($this->tokens, static fn (PasswordResetToken $stored): bool => $stored !== $token),
        );
    }
}
