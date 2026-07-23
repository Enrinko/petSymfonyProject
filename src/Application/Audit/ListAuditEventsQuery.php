<?php

declare(strict_types=1);

namespace App\Application\Audit;

final readonly class ListAuditEventsQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 30,
        public ?string $action = null,
        public ?string $actorEmail = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
