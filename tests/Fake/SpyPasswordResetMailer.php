<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Application\PasswordReset\PasswordResetMailerInterface;
use App\Domain\User\User;

final class SpyPasswordResetMailer implements PasswordResetMailerInterface
{
    /**
     * @var list<array{user: User, rawToken: string}>
     */
    public array $sent = [];

    public function sendResetLink(User $user, string $rawToken): void
    {
        $this->sent[] = ['user' => $user, 'rawToken' => $rawToken];
    }
}
