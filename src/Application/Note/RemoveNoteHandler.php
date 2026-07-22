<?php

declare(strict_types=1);

namespace App\Application\Note;

use App\Domain\Note\Exception\NoteNotFoundException;
use App\Domain\Note\NoteRepositoryInterface;

final readonly class RemoveNoteHandler
{
    public function __construct(
        private NoteRepositoryInterface $notes,
    ) {
    }

    /**
     * @throws NoteNotFoundException
     */
    public function __invoke(int $noteId): void
    {
        $note = $this->notes->find($noteId)
            ?? throw new NoteNotFoundException(sprintf('Note #%d not found.', $noteId));

        $this->notes->remove($note);
    }
}
