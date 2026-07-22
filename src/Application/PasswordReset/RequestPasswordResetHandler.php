<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use App\Domain\PasswordReset\PasswordResetToken;
use App\Domain\PasswordReset\PasswordResetTokenRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;

final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordResetTokenRepositoryInterface $tokens,
        private PasswordResetMailerInterface $mailer,
    ) {
    }

    public function __invoke(RequestPasswordResetCommand $command): void
    {
        $user = $this->users->findByEmail(mb_strtolower(trim($command->email)));

        if ($user === null) {
            // Не раскрываем, зарегистрирован ли email: ответ всегда одинаковый.
            return;
        }

        $now = new \DateTimeImmutable();
        $this->tokens->deleteExpired($now);
        $this->tokens->deleteForUser($user);

        $rawToken = bin2hex(random_bytes(32));
        $this->tokens->save(PasswordResetToken::issueFor($user, $rawToken, $now));

        $this->mailer->sendResetLink($user, $rawToken);
    }
}
