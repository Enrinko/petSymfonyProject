<?php

declare(strict_types=1);

namespace App\Application\Note;

use App\Domain\Note\Note;

final readonly class NoteView
{
    private function __construct(
        public int $id,
        public string $content,
        public int $authorId,
        public string $authorEmail,
        public string $createdAt,
        public ?string $updatedAt,
        public bool $manageable,
    ) {
    }

    public static function fromNote(Note $note, bool $manageable): self
    {
        return new self(
            (int) $note->getId(),
            $note->getContent(),
            (int) $note->getAuthor()->getId(),
            $note->getAuthor()->getEmail(),
            $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
            $note->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            $manageable,
        );
    }
}
