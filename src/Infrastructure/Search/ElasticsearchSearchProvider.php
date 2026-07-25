<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\ClientHitView;
use App\Application\Search\NoteHitView;
use App\Application\Search\SearchProviderInterface;
use App\Application\Search\SearchResults;
use App\Application\Search\TagHitView;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\Tag\TagRepositoryInterface;
use App\Domain\Tag\TagUsage;
use App\Domain\User\User;
use Elastic\Elasticsearch\Client as Elasticsearch;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;

/**
 * ES-движок: fuzziness=AUTO (опечатки), русская морфология, ранжирование,
 * сниппеты из highlight. Схема search-then-hydrate: индекс отдаёт id и
 * фрагменты, данные для отображения поднимаются из БД — индекс без
 * денормализации, устаревание не показывает пользователю старых имён.
 * Теги в ES не индексируются: их мало, ILIKE по ним не деградирует.
 */
final readonly class ElasticsearchSearchProvider implements SearchProviderInterface
{
    private const int GROUP_LIMIT = 5;

    public function __construct(
        private Elasticsearch $client,
        private ClientRepositoryInterface $clients,
        private NoteRepositoryInterface $notes,
        private TagRepositoryInterface $tags,
    ) {
    }

    public function search(string $query, ?User $owner): SearchResults
    {
        $ownerId = $owner?->getId();

        $clientHits = $this->fetchHits(SearchQueries::clients($query, $ownerId, self::GROUP_LIMIT));
        $noteHits = $this->fetchHits(SearchQueries::notes($query, $ownerId, self::GROUP_LIMIT));

        return new SearchResults(
            $this->hydrateClients(array_keys($clientHits), $owner),
            array_map(
                static fn (TagUsage $usage): TagHitView => TagHitView::fromUsage($usage),
                $this->tags->searchVisibleTop($query, $owner, self::GROUP_LIMIT),
            ),
            $this->hydrateNotes($noteHits, $owner, $query),
        );
    }

    /**
     * @param array{index: string, body: array<string, mixed>} $params
     *
     * @return array<int, ?string> id => highlight-фрагмент (в порядке релевантности ES)
     */
    private function fetchHits(array $params): array
    {
        $response = $this->client->search($params);
        \assert($response instanceof ElasticsearchResponse);

        /** @var array{hits?: array{hits?: list<array{_id: string, highlight?: array{content?: list<string>}}>}} $data */
        $data = $response->asArray();

        $hits = [];

        foreach ($data['hits']['hits'] ?? [] as $hit) {
            $hits[(int) $hit['_id']] = $hit['highlight']['content'][0] ?? null;
        }

        return $hits;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<ClientHitView>
     */
    private function hydrateClients(array $ids, ?User $owner): array
    {
        $views = [];

        foreach ($this->clients->findByIds($ids) as $client) {
            // Повторная проверка владельца: страховка от устаревшего индекса
            if ($owner !== null && $client->getOwner()->getId() !== $owner->getId()) {
                continue;
            }

            $views[(int) $client->getId()] = ClientHitView::fromClient($client);
        }

        return self::inEsOrder($ids, $views);
    }

    /**
     * @param array<int, ?string> $hits id => highlight-фрагмент
     *
     * @return list<NoteHitView>
     */
    private function hydrateNotes(array $hits, ?User $owner, string $query): array
    {
        $views = [];

        foreach ($this->notes->findByIds(array_keys($hits)) as $note) {
            if ($owner !== null && $note->getClient()->getOwner()->getId() !== $owner->getId()) {
                continue;
            }

            $noteId = (int) $note->getId();
            $fragment = $hits[$noteId] ?? null;

            $views[$noteId] = $fragment !== null && $fragment !== ''
                ? NoteHitView::fromNoteWithSnippet($note, $fragment)
                : NoteHitView::fromNote($note, $query);
        }

        return self::inEsOrder(array_keys($hits), $views);
    }

    /**
     * БД возвращает сущности в своём порядке — восстанавливаем порядок
     * релевантности ES; пропавшие из БД id молча выпадают.
     *
     * @template T
     *
     * @param list<int>     $ids
     * @param array<int, T> $views
     *
     * @return list<T>
     */
    private static function inEsOrder(array $ids, array $views): array
    {
        $ordered = [];

        foreach ($ids as $id) {
            if (isset($views[$id])) {
                $ordered[] = $views[$id];
            }
        }

        return $ordered;
    }
}
