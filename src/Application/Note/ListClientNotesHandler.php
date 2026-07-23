<?php

declare(strict_types=1);

namespace App\Application\Note;

use App\Domain\Note\NoteRepositoryInterface;

final readonly class ListClientNotesHandler
{
    private const int MAX_LIMIT = 100;

    public function __construct(
        private NoteRepositoryInterface $notes,
    ) {
    }

    public function __invoke(ListClientNotesQuery $query): NotesPage
    {
        $page = max(1, $query->page);
        $limit = min(max(1, $query->limit), self::MAX_LIMIT);

        return new NotesPage(
            $this->notes->findPageByClient($query->client, $page, $limit),
            $this->notes->countByClient($query->client),
            $page,
            $limit,
        );
    }
}
