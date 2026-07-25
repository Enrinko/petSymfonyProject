<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RequestPasswordResetCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'auth.email.blank')]
        #[Assert\Email(message: 'auth.email.invalid')]
        public string $email,
    ) {
    }
}
