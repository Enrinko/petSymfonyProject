<?php

declare(strict_types=1);

namespace App\Application\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterUserCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите email.')]
        #[Assert\Email(message: 'Некорректный email.')]
        #[Assert\Length(max: 180, maxMessage: 'Email не может быть длиннее {{ limit }} символов.')]
        public string $email,
        #[Assert\NotBlank(message: 'Укажите пароль.')]
        #[Assert\Length(min: 10, minMessage: 'Пароль должен содержать минимум {{ limit }} символов.')]
        #[Assert\PasswordStrength(message: 'Пароль слишком простой: добавьте длины и разнообразия символов.')]
        #[Assert\NotCompromisedPassword(message: 'Этот пароль встречался в утечках данных — выберите другой.', skipOnError: true)]
        public string $password,
        #[Assert\IdenticalTo(propertyPath: 'password', message: 'Пароли не совпадают.')]
        public string $passwordConfirm,
    ) {
    }
}
