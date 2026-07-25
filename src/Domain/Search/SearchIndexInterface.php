<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Domain\Client\Client;
use App\Domain\Note\Note;

/**
 * Порт поискового индекса (Elasticsearch в проде).
 * Клиенты не удаляются из индекса: архив — это флаг, палитра ищет и по архиву.
 */
interface SearchIndexInterface
{
    /**
     * Создать индексы с явными маппингами, если их ещё нет.
     */
    public function ensureIndices(): void;

    /**
     * Снести и создать заново — для полной переиндексации.
     */
    public function recreateIndices(): void;

    /**
     * @param iterable<Client> $clients
     */
    public function indexClients(iterable $clients): void;

    /**
     * @param iterable<Note> $notes
     */
    public function indexNotes(iterable $notes): void;

    public function removeNote(int $noteId): void;
}
