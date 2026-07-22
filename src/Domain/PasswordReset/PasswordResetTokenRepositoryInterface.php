<?php

declare(strict_types=1);

namespace App\Domain\PasswordReset;

use App\Domain\User\User;

interface PasswordResetTokenRepositoryInterface
{
    public function findValidByHash(string $tokenHash, \DateTimeImmutable $now): ?PasswordResetToken;

    public function deleteForUser(User $user): void;

    /**
     * @return int число удалённых токенов
     */
    public function deleteExpired(\DateTimeImmutable $now): int;

    public function save(PasswordResetToken $token): void;

    public function remove(PasswordResetToken $token): void;
}
