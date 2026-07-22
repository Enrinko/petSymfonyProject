<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RequestPasswordResetCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите email.')]
        #[Assert\Email(message: 'Некорректный email.')]
        public string $email,
    ) {
    }
}
