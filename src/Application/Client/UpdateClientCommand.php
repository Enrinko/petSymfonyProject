<?php

declare(strict_types=1);

namespace App\Application\Client;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateClientCommand
{
    public function __construct(
        public int $clientId,
        #[Assert\NotBlank(message: 'Укажите имя.')]
        #[Assert\Length(min: 2, minMessage: 'Имя должно содержать минимум {{ limit }} символа.')]
        #[Assert\Length(max: 180, maxMessage: 'Имя не может быть длиннее {{ limit }} символов.')]
        public string $name,
        #[Assert\Email(message: 'Некорректный email.')]
        #[Assert\Length(max: 180, maxMessage: 'Email не может быть длиннее {{ limit }} символов.')]
        public ?string $email = null,
        #[Assert\Length(max: 32, maxMessage: 'Телефон не может быть длиннее {{ limit }} символов.')]
        #[Assert\Regex(pattern: '/^\+?[\d\s()-]{5,31}$/', message: 'Некорректный телефон: цифры, пробелы, скобки и дефисы, минимум 5 цифр.')]
        public ?string $phone = null,
        #[Assert\Length(max: 10000, maxMessage: 'Комментарий не может быть длиннее {{ limit }} символов.')]
        public ?string $comment = null,
    ) {
    }
}
