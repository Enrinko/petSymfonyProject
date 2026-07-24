<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventRepositoryInterface;
use App\Domain\Audit\AuditFilter;

final class InMemoryAuditEventRepository implements AuditEventRepositoryInterface
{
    /** @var list<AuditEvent> */
    public array $saved = [];

    public function save(AuditEvent $event): void
    {
        $this->saved[] = $event;
    }

    public function findPage(AuditFilter $filter, int $page, int $perPage): array
    {
        $matching = array_values(array_filter(
            $this->saved,
            fn (AuditEvent $event): bool => $filter->action === null || $event->getAction() === $filter->action,
        ));

        return \array_slice($matching, ($page - 1) * $perPage, $perPage);
    }

    public function countByFilter(AuditFilter $filter): int
    {
        return \count($this->findPage($filter, 1, \PHP_INT_MAX));
    }

    public function pruneOlderThan(\DateTimeImmutable $threshold): int
    {
        $before = \count($this->saved);
        $this->saved = array_values(array_filter(
            $this->saved,
            fn (AuditEvent $event): bool => $event->getOccurredAt() >= $threshold,
        ));

        return $before - \count($this->saved);
    }
}
