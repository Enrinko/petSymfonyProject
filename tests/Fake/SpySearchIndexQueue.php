<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Application\Search\SearchIndexQueueInterface;

final class SpySearchIndexQueue implements SearchIndexQueueInterface
{
    /** @var list<int> */
    public array $queuedClients = [];

    /** @var list<int> */
    public array $queuedNotes = [];

    /** @var list<int> */
    public array $queuedNoteRemovals = [];

    public function queueClient(int $clientId): void
    {
        $this->queuedClients[] = $clientId;
    }

    public function queueNote(int $noteId): void
    {
        $this->queuedNotes[] = $noteId;
    }

    public function queueNoteRemoval(int $noteId): void
    {
        $this->queuedNoteRemovals[] = $noteId;
    }
}
