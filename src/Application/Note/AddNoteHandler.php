<?php

declare(strict_types=1);

namespace App\Application\Note;

use App\Application\Search\SearchIndexQueueInterface;
use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\User\User;

final readonly class AddNoteHandler
{
    public function __construct(
        private NoteRepositoryInterface $notes,
        private SearchIndexQueueInterface $searchIndexQueue,
    ) {
    }

    public function __invoke(AddNoteCommand $command, Client $client, User $author): Note
    {
        $note = Note::create($client, $author, $command->content, new \DateTimeImmutable());

        $this->notes->save($note);
        $this->searchIndexQueue->queueNote((int) $note->getId());

        return $note;
    }
}
