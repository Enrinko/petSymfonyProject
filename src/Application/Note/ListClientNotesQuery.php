<?php

declare(strict_types=1);

namespace App\Application\Note;

use App\Domain\Client\Client;

final readonly class ListClientNotesQuery
{
    public function __construct(
        public Client $client,
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}
