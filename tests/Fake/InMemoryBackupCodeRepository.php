<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\User\BackupCode;
use App\Domain\User\BackupCodeRepositoryInterface;
use App\Domain\User\User;

final class InMemoryBackupCodeRepository implements BackupCodeRepositoryInterface
{
    /** @var list<BackupCode> */
    public array $codes = [];

    public function findActiveByUser(User $user): array
    {
        return array_values(array_filter(
            $this->codes,
            static fn (BackupCode $code): bool => $code->getUser()->isSameAs($user) && !$code->isUsed(),
        ));
    }

    public function save(BackupCode $code): void
    {
        if (!\in_array($code, $this->codes, true)) {
            $this->codes[] = $code;
        }
    }

    public function removeAllForUser(User $user): void
    {
        $this->codes = array_values(array_filter(
            $this->codes,
            static fn (BackupCode $code): bool => !$code->getUser()->isSameAs($user),
        ));
    }
}
