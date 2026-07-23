<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineInstrumentRepository implements InstrumentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(int $id): ?Instrument
    {
        return $this->entityManager->find(Instrument::class, $id);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Instrument::class, 'i')
            ->andWhere('i.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findByName(string $name): ?Instrument
    {
        return $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Instrument::class, 'i')
            ->andWhere('LOWER(i.name) = :name')
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAll(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(Instrument::class, 'i')
            ->orderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Instrument $instrument): void
    {
        $this->entityManager->persist($instrument);
        $this->entityManager->flush();
    }

    public function remove(Instrument $instrument): void
    {
        $this->entityManager->remove($instrument);
        $this->entityManager->flush();
    }
}
