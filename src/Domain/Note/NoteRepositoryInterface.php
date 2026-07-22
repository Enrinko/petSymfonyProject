<?php

declare(strict_types=1);

namespace App\Domain\Note;

use App\Domain\Client\Client;
use App\Domain\User\User;

interface NoteRepositoryInterface
{
    public function find(int $id): ?Note;

    /**
     * Поиск по тексту заметок (подстрока без регистра); owner ограничивает
     * выборку заметками клиентов этого владельца.
     *
     * @return list<Note>
     */
    public function searchTop(string $query, ?User $owner, int $limit): array;

    /**
     * Свежие сверху (createdAt DESC).
     *
     * @return list<Note>
     */
    public function findPageByClient(Client $client, int $page, int $limit): array;

    public function countByClient(Client $client): int;

    /**
     * Последние заметки по клиентам владельца (для дашборда), свежие сверху.
     *
     * @return list<Note>
     */
    public function findRecentForOwner(?User $owner, int $limit): array;

    public function save(Note $note): void;

    public function remove(Note $note): void;
}
