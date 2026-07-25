<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Client\Client;
use App\Domain\Tag\Tag;
use App\Domain\Tag\TagRepositoryInterface;
use App\Domain\Tag\TagUsage;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTagRepository implements TagRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(int $id): ?Tag
    {
        return $this->entityManager->find(Tag::class, $id);
    }

    public function findByNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Tag::class, 't')
            ->andWhere('t.name IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();
    }

    public function findAllWithUsage(): array
    {
        /** @var list<array{tag: Tag, usageCount: string|int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t AS tag', 'COUNT(c.id) AS usageCount')
            ->from(Tag::class, 't')
            ->leftJoin(Client::class, 'c', 'WITH', 't MEMBER OF c.tags')
            ->groupBy('t.id')
            ->orderBy('usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): TagUsage => new TagUsage($row['tag'], (int) $row['usageCount']),
            $rows,
        );
    }

    public function searchVisibleTop(string $query, ?User $owner, int $limit): array
    {
        // INNER JOIN + COUNT → только теги с ≥1 видимым активным клиентом;
        // usageCount совпадает с тем, что покажет клик по тегу в списке.
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('t AS tag', 'COUNT(c.id) AS usageCount')
            ->from(Tag::class, 't')
            ->join(Client::class, 'c', 'WITH', 't MEMBER OF c.tags')
            ->andWhere("LOWER(t.name) LIKE :query ESCAPE '\\'")
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('query', LikePattern::contains(mb_strtolower($query)))
            ->groupBy('t.id')
            ->orderBy('usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit);

        if ($owner !== null) {
            $queryBuilder
                ->andWhere('c.owner = :owner')
                ->setParameter('owner', $owner);
        }

        /** @var list<array{tag: Tag, usageCount: string|int}> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        return array_map(
            static fn (array $row): TagUsage => new TagUsage($row['tag'], (int) $row['usageCount']),
            $rows,
        );
    }

    public function save(Tag $tag): void
    {
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
    }

    public function remove(Tag $tag): void
    {
        $this->entityManager->remove($tag);
        $this->entityManager->flush();
    }
}
