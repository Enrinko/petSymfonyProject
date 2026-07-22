<?php

declare(strict_types=1);

namespace App\Application\Note;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddNoteCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'Заметка не может быть пустой.', normalizer: 'trim')]
        #[Assert\Length(max: 10000, maxMessage: 'Заметка не может быть длиннее {{ limit }} символов.')]
        public string $content,
    ) {
    }
}
