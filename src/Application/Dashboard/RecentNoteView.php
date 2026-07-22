<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Domain\Note\Note;

final readonly class RecentNoteView
{
    private const int PREVIEW_LENGTH = 90;

    private function __construct(
        public int $noteId,
        public int $clientId,
        public string $clientName,
        public string $preview,
        public string $createdAt,
    ) {
    }

    public static function fromNote(Note $note): self
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', $note->getContent()));

        if (mb_strlen($flat) > self::PREVIEW_LENGTH) {
            $flat = mb_substr($flat, 0, self::PREVIEW_LENGTH) . '…';
        }

        return new self(
            (int) $note->getId(),
            (int) $note->getClient()->getId(),
            $note->getClient()->getName(),
            $flat,
            $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
