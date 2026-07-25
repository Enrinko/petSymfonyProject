<?php

declare(strict_types=1);

namespace App\Application\Profile;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\PasswordReset\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private AuditLoggerInterface $audit,
        private PasswordResetTokenRepositoryInterface $resetTokens,
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

        // Гасим ещё не использованные ссылки сброса: сменивший пароль пользователь
        // мог заподозрить компрометацию почты — «висящий» токен не должен откатывать
        $this->resetTokens->deleteForUser($user);

        $this->audit->log(AuditAction::PasswordChanged, 'user', (string) $user->getId());
    }
}
