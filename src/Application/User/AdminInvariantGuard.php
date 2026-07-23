<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\Exception\LastActiveAdminException;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

/**
 * Инвариант админ-модели: в системе всегда остаётся ≥ 1 активный администратор.
 * Единственная точка проверки для смены ролей и деактивации.
 */
final readonly class AdminInvariantGuard
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * @param list<string> $newRoles
     *
     * @throws LastActiveAdminException
     */
    public function assertCanChangeRoles(User $target, array $newRoles): void
    {
        $dropsAdmin = $target->isAdmin() && !\in_array(Role::Admin->value, $newRoles, true);

        // Роль теряет только активный админ — неактивный в инварианте не участвует
        if ($dropsAdmin && $target->isActive() && !$this->hasBackupAdmin($target)) {
            throw new LastActiveAdminException('Cannot strip the admin role from the last active administrator.');
        }
    }

    /**
     * @throws LastActiveAdminException
     */
    public function assertCanDeactivate(User $target): void
    {
        if ($target->isAdmin() && $target->isActive() && !$this->hasBackupAdmin($target)) {
            throw new LastActiveAdminException('Cannot deactivate the last active administrator.');
        }
    }

    private function hasBackupAdmin(User $target): bool
    {
        $targetId = $target->getId();
        \assert($targetId !== null, 'Invariant checks require a persisted user.');

        return $this->users->countActiveAdminsExcept($targetId) > 0;
    }
}
