<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\Search\SearchIndexInterface;

final class SpySearchIndex implements SearchIndexInterface
{
    public int $ensured = 0;

    public int $recreated = 0;

    /** @var list<list<Client>> батчи bulk-записи — размер чанка виден снаружи */
    public array $clientBatches = [];

    /** @var list<list<Note>> */
    public array $noteBatches = [];

    /** @var list<int> */
    public array $removedNotes = [];

    public function ensureIndices(): void
    {
        ++$this->ensured;
    }

    public function recreateIndices(): void
    {
        ++$this->recreated;
    }

    public function indexClients(iterable $clients): void
    {
        $this->clientBatches[] = array_values([...$clients]);
    }

    public function indexNotes(iterable $notes): void
    {
        $this->noteBatches[] = array_values([...$notes]);
    }

    public function removeNote(int $noteId): void
    {
        $this->removedNotes[] = $noteId;
    }
}
