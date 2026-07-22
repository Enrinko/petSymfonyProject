<?php

declare(strict_types=1);

namespace App\Domain\Note;

use App\Domain\Client\Client;

interface NoteRepositoryInterface
{
    public function find(int $id): ?Note;

    /**
     * Свежие сверху (createdAt DESC).
     *
     * @return list<Note>
     */
    public function findPageByClient(Client $client, int $page, int $limit): array;

    public function countByClient(Client $client): int;

    public function save(Note $note): void;

    public function remove(Note $note): void;
}
