<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNoteRepository implements NoteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(int $id): ?Note
    {
        return $this->entityManager->find(Note::class, $id);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(Note::class, 'n')
            ->andWhere('n.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function searchTop(string $query, ?User $owner, int $limit): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(Note::class, 'n')
            ->join('n.client', 'c')
            ->andWhere('LOWER(n.content) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit);

        if ($owner !== null) {
            $queryBuilder
                ->andWhere('c.owner = :owner')
                ->setParameter('owner', $owner);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findPageByClient(Client $client, int $page, int $limit): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(Note::class, 'n')
            ->andWhere('n.client = :client')
            ->setParameter('client', $client)
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByClient(Client $client): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Note::class, 'n')
            ->andWhere('n.client = :client')
            ->setParameter('client', $client)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecentForOwner(?User $owner, int $limit): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(Note::class, 'n')
            ->join('n.client', 'c')
            ->orderBy('n.createdAt', 'DESC')
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit);

        if ($owner !== null) {
            $queryBuilder
                ->andWhere('c.owner = :owner')
                ->setParameter('owner', $owner);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function iterateAll(): iterable
    {
        return $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(Note::class, 'n')
            ->orderBy('n.id', 'ASC')
            ->getQuery()
            ->toIterable();
    }

    public function save(Note $note): void
    {
        $this->entityManager->persist($note);
        $this->entityManager->flush();
    }

    public function remove(Note $note): void
    {
        $this->entityManager->remove($note);
        $this->entityManager->flush();
    }
}
