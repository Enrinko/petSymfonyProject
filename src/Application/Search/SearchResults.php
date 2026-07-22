<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchResults
{
    /**
     * @param list<ClientHitView> $clients
     * @param list<TagHitView>    $tags
     * @param list<NoteHitView>   $notes
     */
    public function __construct(
        public array $clients,
        public array $tags,
        public array $notes,
    ) {
    }
}
