<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    /**
     * @throws EmailAlreadyInUseException
     */
    public function __invoke(RegisterUserCommand $command): User
    {
        $email = mb_strtolower(trim($command->email));

        if ($this->users->findByEmail($email) !== null) {
            throw new EmailAlreadyInUseException(sprintf('Email "%s" is already registered.', $email));
        }

        $hashedPassword = $this->passwordHasherFactory
            ->getPasswordHasher(User::class)
            ->hash($command->password);

        $user = User::register($email, $hashedPassword);
        $this->users->save($user);

        return $user;
    }
}
