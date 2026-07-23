<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Event\Event;
use App\Domain\Event\EventRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEventRepository implements EventRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(int $id): ?Event
    {
        return $this->entityManager->find(Event::class, $id);
    }

    public function findUpcoming(\DateTimeImmutable $from): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->andWhere('e.date >= :from')
            ->setParameter('from', $from)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPast(\DateTimeImmutable $before): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->andWhere('e.date < :before')
            ->setParameter('before', $before)
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(Event $event): void
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    public function remove(Event $event): void
    {
        $this->entityManager->remove($event);
        $this->entityManager->flush();
    }
}
