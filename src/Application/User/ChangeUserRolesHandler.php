<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\Exception\CannotRemoveOwnAdminRoleException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

final readonly class ChangeUserRolesHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * @throws UserNotFoundException
     * @throws CannotRemoveOwnAdminRoleException
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

        $user->changeRoles($command->roles);
        $this->users->save($user);

        return $user;
    }
}
