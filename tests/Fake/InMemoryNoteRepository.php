<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;

final class InMemoryNoteRepository implements NoteRepositoryInterface
{
    /**
     * @var array<int, Note>
     */
    private array $byId = [];

    /**
     * @var list<Note>
     */
    public array $saved = [];

    /**
     * @var list<Note>
     */
    public array $removed = [];

    public function withNote(int $id, Note $note): self
    {
        $this->byId[$id] = $note;

        return $this;
    }

    public function find(int $id): ?Note
    {
        return $this->byId[$id] ?? null;
    }

    public function findPageByClient(Client $client, int $page, int $limit): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Note $note): bool => $note->getClient() === $client,
        ));
    }

    public function countByClient(Client $client): int
    {
        return \count($this->findPageByClient($client, 1, PHP_INT_MAX));
    }

    public function save(Note $note): void
    {
        $this->saved[] = $note;
    }

    public function remove(Note $note): void
    {
        $this->removed[] = $note;
    }
}
