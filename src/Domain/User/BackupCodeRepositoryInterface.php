<?php

declare(strict_types=1);

namespace App\Domain\User;

interface BackupCodeRepositoryInterface
{
    /** @return list<BackupCode> неиспользованные коды пользователя */
    public function findActiveByUser(User $user): array;

    public function save(BackupCode $code): void;

    /** Стереть все коды пользователя (regenerate/disable). */
    public function removeAllForUser(User $user): void;
}
