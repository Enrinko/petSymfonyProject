<?php

declare(strict_types=1);

namespace App\Application\Note;

use App\Domain\Note\Note;

/**
 * Срез ленты заметок. Отдаёт сущности: пер-заметочное право manage
 * (voter) знает только HTTP-слой — он и собирает NoteView.
 */
final readonly class NotesPage
{
    /**
     * @param list<Note> $notes
     */
    public function __construct(
        public array $notes,
        public int $total,
        public int $page,
        public int $limit,
    ) {
    }
}
