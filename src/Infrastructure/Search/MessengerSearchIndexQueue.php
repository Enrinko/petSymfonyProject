<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\Message\IndexClientMessage;
use App\Application\Search\Message\IndexNoteMessage;
use App\Application\Search\Message\RemoveNoteFromIndexMessage;
use App\Application\Search\SearchIndexQueueInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Doctrine-транспорт живёт в той же БД, что и сущности: dispatch внутри
 * транзакции (например, CSV-импорт) откатывается вместе с ней — индекс
 * не получит сообщений о клиентах, которых не стало.
 */
final readonly class MessengerSearchIndexQueue implements SearchIndexQueueInterface
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function queueClient(int $clientId): void
    {
        $this->bus->dispatch(new IndexClientMessage($clientId));
    }

    public function queueNote(int $noteId): void
    {
        $this->bus->dispatch(new IndexNoteMessage($noteId));
    }

    public function queueNoteRemoval(int $noteId): void
    {
        $this->bus->dispatch(new RemoveNoteFromIndexMessage($noteId));
    }
}
