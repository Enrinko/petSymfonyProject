<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\Message\IndexClientMessage;
use App\Application\Search\Message\IndexNoteMessage;
use App\Application\Search\Message\RemoveNoteFromIndexMessage;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\Search\SearchIndexInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Воркер индексации: поднимает сущность из БД и пишет в индекс.
 * Сущности уже нет — молча пропускаем (сообщение могло пережить удаление).
 * Недоступный ES роняет обработку → штатные ретраи Messenger, затем failed.
 */
final readonly class SearchIndexMessageHandler
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private NoteRepositoryInterface $notes,
        private SearchIndexInterface $index,
    ) {
    }

    #[AsMessageHandler]
    public function indexClient(IndexClientMessage $message): void
    {
        $client = $this->clients->find($message->clientId);

        if ($client === null) {
            return;
        }

        $this->index->ensureIndices();
        $this->index->indexClients([$client]);
    }

    #[AsMessageHandler]
    public function indexNote(IndexNoteMessage $message): void
    {
        $note = $this->notes->find($message->noteId);

        if ($note === null) {
            return;
        }

        $this->index->ensureIndices();
        $this->index->indexNotes([$note]);
    }

    #[AsMessageHandler]
    public function removeNote(RemoveNoteFromIndexMessage $message): void
    {
        $this->index->ensureIndices();
        $this->index->removeNote($message->noteId);
    }
}
