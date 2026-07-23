<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\User\Exception\CannotDeactivateSelfException;
use App\Domain\User\Exception\LastActiveAdminException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

final readonly class ChangeUserStatusHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AdminInvariantGuard $guard,
        private AuditLoggerInterface $audit,
    ) {
    }

    /**
     * @throws UserNotFoundException
     * @throws CannotDeactivateSelfException
     * @throws LastActiveAdminException
     */
    public function __invoke(ChangeUserStatusCommand $command): User
    {
        $user = $this->users->findById($command->userId)
            ?? throw new UserNotFoundException(sprintf('User #%d not found.', $command->userId));

        if ($command->active) {
            $user->activate();
        } else {
            if ($command->userId === $command->actorId) {
                throw new CannotDeactivateSelfException('An administrator cannot deactivate their own account.');
            }

            $this->guard->assertCanDeactivate($user);
            $user->deactivate();
        }

        $this->users->save($user);

        $this->audit->log(
            $command->active ? AuditAction::UserActivated : AuditAction::UserDeactivated,
            'user',
            (string) $user->getId(),
        );

        return $user;
    }
}
