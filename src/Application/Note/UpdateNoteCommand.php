<?php

declare(strict_types=1);

namespace App\Application\Note;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateNoteCommand
{
    public function __construct(
        public int $noteId,
        #[Assert\NotBlank(message: 'Заметка не может быть пустой.', normalizer: 'trim')]
        #[Assert\Length(max: 10000, maxMessage: 'Заметка не может быть длиннее {{ limit }} символов.')]
        public string $content,
    ) {
    }
}
