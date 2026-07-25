<?php

declare(strict_types=1);

namespace App\Application\Search;

/**
 * Постановка индексации в очередь. Вызывается из use-case-хендлеров после
 * сохранения; сам индекс обновляет воркер — HTTP-ответ не ждёт Elasticsearch.
 */
interface SearchIndexQueueInterface
{
    public function queueClient(int $clientId): void;

    public function queueNote(int $noteId): void;

    public function queueNoteRemoval(int $noteId): void;
}
