<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\Search\SearchIndexInterface;
use Elastic\Elasticsearch\Client as Elasticsearch;
use Elastic\Elasticsearch\Exception\ClientResponseException;

final class ElasticsearchSearchIndex implements SearchIndexInterface
{
    /**
     * Один exists-чек на процесс: воркер живёт --time-limit=3600,
     * дёргать HEAD перед каждым сообщением незачем.
     */
    private bool $indicesEnsured = false;

    public function __construct(
        private readonly Elasticsearch $client,
    ) {
    }

    public function ensureIndices(): void
    {
        if ($this->indicesEnsured) {
            return;
        }

        foreach (self::indexBodies() as $index => $body) {
            /** @var \Elastic\Elasticsearch\Response\Elasticsearch $exists */
            $exists = $this->client->indices()->exists(['index' => $index]);

            if (!$exists->asBool()) {
                $this->client->indices()->create(['index' => $index, 'body' => $body]);
            }
        }

        $this->indicesEnsured = true;
    }

    public function recreateIndices(): void
    {
        foreach (self::indexBodies() as $index => $body) {
            try {
                $this->client->indices()->delete(['index' => $index]);
            } catch (ClientResponseException $e) {
                if ($e->getResponse()->getStatusCode() !== 404) {
                    throw $e;
                }
            }

            $this->client->indices()->create(['index' => $index, 'body' => $body]);
        }

        $this->indicesEnsured = true;
    }

    public function indexClients(iterable $clients): void
    {
        $operations = [];

        foreach ($clients as $client) {
            $operations[] = ['index' => ['_index' => SearchDocuments::CLIENTS_INDEX, '_id' => (string) $client->getId()]];
            $operations[] = SearchDocuments::clientDocument($client);
        }

        $this->bulk($operations);
    }

    public function indexNotes(iterable $notes): void
    {
        $operations = [];

        foreach ($notes as $note) {
            $operations[] = ['index' => ['_index' => SearchDocuments::NOTES_INDEX, '_id' => (string) $note->getId()]];
            $operations[] = SearchDocuments::noteDocument($note);
        }

        $this->bulk($operations);
    }

    public function removeNote(int $noteId): void
    {
        try {
            $this->client->delete(['index' => SearchDocuments::NOTES_INDEX, 'id' => (string) $noteId]);
        } catch (ClientResponseException $e) {
            // Заметки уже нет в индексе — удаление идемпотентно
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $operations
     */
    private function bulk(array $operations): void
    {
        if ($operations === []) {
            return;
        }

        // refresh=wait_for не нужен: поиск не обещает read-your-writes,
        // стандартного refresh_interval в 1 с достаточно
        $this->client->bulk(['body' => $operations]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function indexBodies(): array
    {
        return [
            SearchDocuments::CLIENTS_INDEX => SearchDocuments::clientsIndexBody(),
            SearchDocuments::NOTES_INDEX => SearchDocuments::notesIndexBody(),
        ];
    }
}
