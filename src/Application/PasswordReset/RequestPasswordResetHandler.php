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

        // Токен генерируется в обеих ветках: выравниваем работу, чтобы время
        // ответа не выдавало, зарегистрирован ли email. Отправка письма
        // асинхронна (messenger), поэтому SMTP на тайминг тоже не влияет.
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = PasswordResetToken::hashOf($rawToken);
        \assert($tokenHash !== '');

        if ($user === null) {
            // Не раскрываем, зарегистрирован ли email: ответ всегда одинаковый.
            return;
        }

        $now = new \DateTimeImmutable();
        $this->tokens->deleteExpired($now);
        $this->tokens->deleteForUser($user);

        $this->tokens->save(PasswordResetToken::issueFor($user, $rawToken, $now));

        $this->mailer->sendResetLink($user, $rawToken);
    }
}
