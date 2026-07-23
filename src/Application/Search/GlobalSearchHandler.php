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
 * Палитра Ctrl+K: до 5 совпадений на группу, поиск и по архивным
 * (сценарий «найти и перейти» важнее фильтров списка).
 */
final readonly class GlobalSearchHandler
{
    private const int MIN_QUERY_LENGTH = 2;
    private const int GROUP_LIMIT = 5;

    public function __construct(
        private ClientRepositoryInterface $clients,
        private NoteRepositoryInterface $notes,
        private TagRepositoryInterface $tags,
    ) {
    }

    public function __invoke(string $query, ?User $owner): SearchResults
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return new SearchResults([], [], []);
        }

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
