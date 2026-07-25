<?php

declare(strict_types=1);

namespace App\Application\Note;

use App\Application\Search\SearchIndexQueueInterface;
use App\Domain\Note\Exception\NoteNotFoundException;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;

final readonly class UpdateNoteHandler
{
    public function __construct(
        private NoteRepositoryInterface $notes,
        private SearchIndexQueueInterface $searchIndexQueue,
    ) {
    }

    /**
     * @throws NoteNotFoundException
     */
    public function __invoke(UpdateNoteCommand $command): Note
    {
        $note = $this->notes->find($command->noteId)
            ?? throw new NoteNotFoundException(sprintf('Note #%d not found.', $command->noteId));

        $note->updateContent($command->content, new \DateTimeImmutable());
        $this->notes->save($note);
        $this->searchIndexQueue->queueNote($command->noteId);

        return $note;
    }
}
