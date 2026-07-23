<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use App\Domain\User\User;

interface PasswordResetMailerInterface
{
    public function sendResetLink(User $user, string $rawToken): void;
}
