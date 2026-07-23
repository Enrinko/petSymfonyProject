<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventRepositoryInterface;
use App\Domain\Audit\AuditFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineAuditEventRepository implements AuditEventRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(AuditEvent $event): void
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    public function findPage(AuditFilter $filter, int $page, int $perPage): array
    {
        return $this->createFilterQueryBuilder($filter)
            ->orderBy('a.occurredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countByFilter(AuditFilter $filter): int
    {
        return (int) $this->createFilterQueryBuilder($filter)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function pruneOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(AuditEvent::class, 'a')
            ->where('a.occurredAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    private function createFilterQueryBuilder(AuditFilter $filter): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AuditEvent::class, 'a');

        if ($filter->action !== null && $filter->action !== '') {
            $queryBuilder->andWhere('a.action = :action')->setParameter('action', $filter->action);
        }

        if ($filter->actorEmail !== null && $filter->actorEmail !== '') {
            $queryBuilder
                ->andWhere('LOWER(a.actorEmail) LIKE :actor')
                ->setParameter('actor', '%' . mb_strtolower($filter->actorEmail) . '%');
        }

        if ($filter->from !== null) {
            $queryBuilder->andWhere('a.occurredAt >= :from')->setParameter('from', $filter->from);
        }

        if ($filter->to !== null) {
            $queryBuilder->andWhere('a.occurredAt <= :to')->setParameter('to', $filter->to);
        }

        return $queryBuilder;
    }
}
