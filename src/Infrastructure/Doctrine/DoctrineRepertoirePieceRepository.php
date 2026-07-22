<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Client\Client;
use App\Domain\Repertoire\RepertoirePiece;
use App\Domain\Repertoire\RepertoirePieceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRepertoirePieceRepository implements RepertoirePieceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(int $id): ?RepertoirePiece
    {
        return $this->entityManager->find(RepertoirePiece::class, $id);
    }

    public function findByClient(Client $client): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(RepertoirePiece::class, 'p')
            ->andWhere('p.client = :client')
            ->setParameter('client', $client)
            ->orderBy('p.startedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(RepertoirePiece $piece): void
    {
        $this->entityManager->persist($piece);
        $this->entityManager->flush();
    }

    public function remove(RepertoirePiece $piece): void
    {
        $this->entityManager->remove($piece);
        $this->entityManager->flush();
    }
}
