<?php

declare(strict_types=1);

namespace App\Application\PasswordReset;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResetPasswordCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'auth.reset.token_blank')]
        public string $token,
        #[Assert\NotBlank(message: 'auth.password.blank')]
        #[Assert\Length(min: 10, minMessage: 'auth.password.too_short')]
        #[Assert\PasswordStrength(message: 'auth.password.weak')]
        #[Assert\NotCompromisedPassword(message: 'auth.password.compromised', skipOnError: true)]
        public string $password,
        #[Assert\IdenticalTo(propertyPath: 'password', message: 'auth.password.mismatch')]
        public string $passwordConfirm,
    ) {
    }
}
