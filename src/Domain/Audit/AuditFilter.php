<?php

declare(strict_types=1);

namespace App\Domain\Audit;

final readonly class AuditFilter
{
    public function __construct(
        public ?string $action = null,
        public ?string $actorEmail = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
    ) {
    }
}
