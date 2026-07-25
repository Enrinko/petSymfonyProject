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

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Client::class, 'c')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findByEmail(string $email, ?User $owner = null): ?Client
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Client::class, 'c')
            ->andWhere('LOWER(c.email) = :email')
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('email', mb_strtolower($email))
            ->setMaxResults(1);

        if ($owner !== null) {
            $queryBuilder
                ->andWhere('c.owner = :owner')
                ->setParameter('owner', $owner);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    public function iterateBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): iterable
    {
        return $this->createSearchQueryBuilder($search, $includeArchived, $owner, $tags)
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->toIterable();
    }

    public function findPage(int $page, int $limit, string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = [], ?int $instrumentId = null): array
    {
        return $this->createSearchQueryBuilder($search, $includeArchived, $owner, $tags, $instrumentId)
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = [], ?int $instrumentId = null): int
    {
        return (int) $this->createSearchQueryBuilder($search, $includeArchived, $owner, $tags, $instrumentId)
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCreatedSince(\DateTimeImmutable $since, ?User $owner = null): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Client::class, 'c')
            ->andWhere('c.archivedAt IS NULL')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('since', $since);

        if ($owner !== null) {
            $queryBuilder
                ->andWhere('c.owner = :owner')
                ->setParameter('owner', $owner);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function save(Client $client): void
    {
        $this->entityManager->persist($client);
        $this->entityManager->flush();
    }

    /**
     * @param list<string> $tags
     */
    private function createSearchQueryBuilder(string $search, bool $includeArchived, ?User $owner, array $tags = [], ?int $instrumentId = null): QueryBuilder
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

        if ($instrumentId !== null) {
            $queryBuilder
                ->join('c.instruments', 'i')
                ->andWhere('i.id = :instrumentId')
                ->setParameter('instrumentId', $instrumentId);
        }

        if (!$includeArchived) {
            $queryBuilder->andWhere('c.archivedAt IS NULL');
        }

        if ($search !== '') {
            $queryBuilder
                ->andWhere("LOWER(c.name) LIKE :search ESCAPE '\\' OR LOWER(c.email) LIKE :search ESCAPE '\\' OR LOWER(c.phone) LIKE :search ESCAPE '\\'")
                ->setParameter('search', LikePattern::contains(mb_strtolower($search)));
        }

        return $queryBuilder;
    }
}
