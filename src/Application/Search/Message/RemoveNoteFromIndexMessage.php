<?php

declare(strict_types=1);

namespace App\Application\Search\Message;

/**
 * Заметки удаляются жёстко (в отличие от клиентов, у которых архив — флаг),
 * поэтому у удаления отдельное сообщение: сущности в БД уже нет.
 */
final readonly class RemoveNoteFromIndexMessage
{
    public function __construct(
        public int $noteId,
    ) {
    }
}
