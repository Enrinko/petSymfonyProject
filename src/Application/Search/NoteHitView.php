<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Note\Note;

final readonly class NoteHitView
{
    private const int CONTEXT = 40;

    private function __construct(
        public int $id,
        public int $clientId,
        public string $clientName,
        public string $snippet,
        public string $createdAt,
    ) {
    }

    public static function fromNote(Note $note, string $query): self
    {
        return new self(
            (int) $note->getId(),
            (int) $note->getClient()->getId(),
            $note->getClient()->getName(),
            self::makeSnippet($note->getContent(), $query),
            $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * ±40 символов вокруг первого вхождения (без учёта регистра),
     * переносы схлопываются — сниппет живёт в одну строку палитры.
     */
    private static function makeSnippet(string $content, string $query): string
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', $content));
        $position = mb_stripos($flat, $query);

        if ($position === false) {
            $position = 0;
        }

        $start = max(0, $position - self::CONTEXT);
        $length = mb_strlen($query) + self::CONTEXT * 2;
        $snippet = mb_substr($flat, $start, $length);

        if ($start > 0) {
            $snippet = '…' . $snippet;
        }

        if ($start + $length < mb_strlen($flat)) {
            $snippet .= '…';
        }

        return $snippet;
    }
}
