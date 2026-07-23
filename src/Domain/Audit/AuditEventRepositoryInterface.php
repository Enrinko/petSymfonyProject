<?php

declare(strict_types=1);

namespace App\Domain\Audit;

interface AuditEventRepositoryInterface
{
    public function save(AuditEvent $event): void;

    /**
     * @return list<AuditEvent>
     */
    public function findPage(AuditFilter $filter, int $page, int $perPage): array;

    public function countByFilter(AuditFilter $filter): int;

    /** @return int удалённых записей */
    public function pruneOlderThan(\DateTimeImmutable $threshold): int;
}
