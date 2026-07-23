<?php

declare(strict_types=1);

namespace App\Application\Profile;

use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    /**
     * @throws InvalidCurrentPasswordException
     */
    public function __invoke(User $user, ChangePasswordCommand $command): void
    {
        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);

        if (!$hasher->verify($user->getPassword(), $command->currentPassword)) {
            throw new InvalidCurrentPasswordException('Current password does not match.');
        }

        $user->changePassword($hasher->hash($command->newPassword));
        $this->users->save($user);
    }
}
