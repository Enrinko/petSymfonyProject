<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\Tag\TagRepositoryInterface;
use App\Domain\Tag\TagUsage;
use App\Domain\User\User;

/**
 * ILIKE-движок (PostgreSQL + pg_trgm): первая версия поиска и вечный
 * фолбэк на случай недоступного Elasticsearch.
 */
final readonly class DatabaseSearchProvider implements SearchProviderInterface
{
    private const int GROUP_LIMIT = 5;

    public function __construct(
        private ClientRepositoryInterface $clients,
        private NoteRepositoryInterface $notes,
        private TagRepositoryInterface $tags,
    ) {
    }

    public function search(string $query, ?User $owner): SearchResults
    {
        return new SearchResults(
            array_map(
                static fn (Client $client): ClientHitView => ClientHitView::fromClient($client),
                $this->clients->findPage(1, self::GROUP_LIMIT, $query, true, $owner),
            ),
            array_map(
                static fn (TagUsage $usage): TagHitView => TagHitView::fromUsage($usage),
                $this->tags->searchVisibleTop($query, $owner, self::GROUP_LIMIT),
            ),
            array_map(
                static fn (Note $note): NoteHitView => NoteHitView::fromNote($note, $query),
                $this->notes->searchTop($query, $owner, self::GROUP_LIMIT),
            ),
        );
    }
}
