<?php

declare(strict_types=1);

namespace App\Application\Profile;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProfileCommand
{
    public function __construct(
        #[Assert\Length(max: 80, maxMessage: 'Имя не может быть длиннее {{ limit }} символов.')]
        public ?string $displayName,
        #[Assert\Choice(choices: ['ru', 'en'], message: 'profile.locale.invalid')]
        public ?string $locale = null,
    ) {
    }
}
