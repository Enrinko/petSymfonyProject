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
     * Сниппет от внешнего движка (ES highlight, plain-текст без разметки):
     * схлопываем переносы и обрамляем многоточиями, если фрагмент — не весь текст.
     */
    public static function fromNoteWithSnippet(Note $note, string $fragment): self
    {
        return new self(
            (int) $note->getId(),
            (int) $note->getClient()->getId(),
            $note->getClient()->getName(),
            self::decorateFragment($note->getContent(), $fragment),
            $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    private static function decorateFragment(string $content, string $fragment): string
    {
        $flatContent = trim((string) preg_replace('/\s+/u', ' ', $content));
        $flatFragment = trim((string) preg_replace('/\s+/u', ' ', $fragment));

        $position = mb_strpos($flatContent, $flatFragment);

        if ($position === false) {
            return $flatFragment;
        }

        $snippet = $flatFragment;

        if ($position > 0) {
            $snippet = '…' . $snippet;
        }

        if ($position + mb_strlen($flatFragment) < mb_strlen($flatContent)) {
            $snippet .= '…';
        }

        return $snippet;
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
