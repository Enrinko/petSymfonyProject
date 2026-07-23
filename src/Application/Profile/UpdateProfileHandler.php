<?php

declare(strict_types=1);

namespace App\Application\Profile;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

final readonly class UpdateProfileHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function __invoke(User $user, UpdateProfileCommand $command): void
    {
        $user->rename($command->displayName);
        $this->users->save($user);
    }
}
