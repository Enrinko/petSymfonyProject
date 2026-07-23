<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\User\Exception\CannotRemoveOwnAdminRoleException;
use App\Domain\User\Exception\LastActiveAdminException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

final readonly class ChangeUserRolesHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AdminInvariantGuard $guard,
        private AuditLoggerInterface $audit,
    ) {
    }

    /**
     * @throws UserNotFoundException
     * @throws CannotRemoveOwnAdminRoleException
     * @throws LastActiveAdminException
     */
    public function __invoke(ChangeUserRolesCommand $command): User
    {
        $user = $this->users->findById($command->userId)
            ?? throw new UserNotFoundException(sprintf('User #%d not found.', $command->userId));

        $isSelf = $command->userId === $command->actorId;
        $dropsAdmin = in_array(Role::Admin->value, $user->getRoles(), true)
            && !in_array(Role::Admin->value, $command->roles, true);

        if ($isSelf && $dropsAdmin) {
            throw new CannotRemoveOwnAdminRoleException('An administrator cannot revoke their own admin role.');
        }

        $this->guard->assertCanChangeRoles($user, $command->roles);

        $oldRoles = $user->getRoles();
        $user->changeRoles($command->roles);
        $this->users->save($user);

        $this->audit->log(AuditAction::RolesChanged, 'user', (string) $user->getId(), [
            'old' => $oldRoles,
            'new' => $user->getRoles(),
        ]);

        return $user;
    }
}
