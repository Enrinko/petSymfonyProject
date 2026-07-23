<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\PasswordReset\Exception\InvalidResetTokenException;
use App\Domain\PasswordReset\PasswordResetToken;
use App\Domain\PasswordReset\PasswordResetTokenRepositoryInterface;
use App\Domain\Shared\TransactionRunnerInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordResetTokenRepositoryInterface $tokens,
        private UserRepositoryInterface $users,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private TransactionRunnerInterface $transactions,
        private AuditLoggerInterface $audit,
    ) {
    }

    /**
     * @throws InvalidResetTokenException
     */
    public function __invoke(ResetPasswordCommand $command): void
    {
        $token = $this->tokens->findValidByHash(
            PasswordResetToken::hashOf($command->token),
            new \DateTimeImmutable(),
        ) ?? throw new InvalidResetTokenException('Reset token is invalid or expired.');

        $hashedPassword = $this->passwordHasherFactory
            ->getPasswordHasher(User::class)
            ->hash($command->password);

        $user = $token->getUser();
        $user->changePassword($hashedPassword);

        // Атомарно: упавшее удаление токена не должно оставить сменённый пароль
        // с всё ещё валидным токеном (повторное использование ссылки).
        $this->transactions->inTransaction(function () use ($user, $token): void {
            $this->users->save($user);
            $this->tokens->remove($token);
        });

        $this->audit->log(AuditAction::PasswordResetCompleted, 'user', (string) $user->getId());
    }
}
