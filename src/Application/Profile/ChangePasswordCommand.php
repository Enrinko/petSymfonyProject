<?php

declare(strict_types=1);

namespace App\Application\Profile;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangePasswordCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите текущий пароль.')]
        public string $currentPassword,
        // Политика новых паролей — та же, что при регистрации
        #[Assert\NotBlank(message: 'Укажите новый пароль.')]
        #[Assert\Length(min: 10, minMessage: 'Пароль должен содержать минимум {{ limit }} символов.')]
        #[Assert\PasswordStrength(message: 'Пароль слишком простой: добавьте длины и разнообразия символов.')]
        #[Assert\NotCompromisedPassword(message: 'Этот пароль встречался в утечках данных — выберите другой.', skipOnError: true)]
        public string $newPassword,
        #[Assert\IdenticalTo(propertyPath: 'newPassword', message: 'Пароли не совпадают.')]
        public string $newPasswordConfirm,
    ) {
    }
}
