<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(int $id): ?Client
    {
        return $this->entityManager->find(Client::class, $id);
    }

    public function findPage(int $page, int $limit, string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): array
    {
        return $this->createSearchQueryBuilder($search, $includeArchived, $owner, $tags)
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): int
    {
        return (int) $this->createSearchQueryBuilder($search, $includeArchived, $owner, $tags)
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Client $client): void
    {
        $this->entityManager->persist($client);
        $this->entityManager->flush();
    }

    /**
     * @param list<string> $tags
     */
    private function createSearchQueryBuilder(string $search, bool $includeArchived, ?User $owner, array $tags = []): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT c')
            ->from(Client::class, 'c');

        if ($owner !== null) {
            $queryBuilder
                ->andWhere('c.owner = :owner')
                ->setParameter('owner', $owner);
        }

        if ($tags !== []) {
            $queryBuilder
                ->join('c.tags', 't')
                ->andWhere('t.name IN (:tagNames)')
                ->setParameter('tagNames', $tags);
        }

        if (!$includeArchived) {
            $queryBuilder->andWhere('c.archivedAt IS NULL');
        }

        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(c.name) LIKE :search OR LOWER(c.email) LIKE :search OR LOWER(c.phone) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $queryBuilder;
    }
}
