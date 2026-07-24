<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\User;

/** Отправка письма со ссылкой подтверждения email. */
interface VerificationMailerInterface
{
    public function sendVerificationLink(User $user): void;
}
