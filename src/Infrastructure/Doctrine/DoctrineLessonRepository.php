<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Client\Client;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonRepositoryInterface;
use App\Domain\Lesson\LessonStatus;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLessonRepository implements LessonRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(int $id): ?Lesson
    {
        return $this->entityManager->find(Lesson::class, $id);
    }

    public function findForTeacherBetween(User $teacher, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->andWhere('l.teacher = :teacher')
            ->andWhere('l.startsAt >= :from')
            ->andWhere('l.startsAt < :to')
            ->setParameter('teacher', $teacher)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('l.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUpcomingForClient(Client $client, \DateTimeImmutable $now, int $limit): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->andWhere('l.client = :client')
            ->andWhere('l.startsAt >= :now')
            ->andWhere('l.status = :planned')
            ->setParameter('client', $client)
            ->setParameter('now', $now)
            ->setParameter('planned', LessonStatus::Planned)
            ->orderBy('l.startsAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPlannedForReminder(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->andWhere('l.status = :planned')
            ->andWhere('l.reminderSentAt IS NULL')
            ->andWhere('l.startsAt >= :from')
            ->andWhere('l.startsAt <= :to')
            ->setParameter('planned', LessonStatus::Planned)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('l.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentClosedForClient(Client $client, int $limit): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->andWhere('l.client = :client')
            ->andWhere('l.status != :planned')
            ->setParameter('client', $client)
            ->setParameter('planned', LessonStatus::Planned)
            ->orderBy('l.startsAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findClosedForClientSince(Client $client, \DateTimeImmutable $since): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->andWhere('l.client = :client')
            ->andWhere('l.status != :planned')
            ->andWhere('l.startsAt >= :since')
            ->setParameter('client', $client)
            ->setParameter('planned', LessonStatus::Planned)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    public function save(Lesson $lesson): void
    {
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();
    }

    public function remove(Lesson $lesson): void
    {
        $this->entityManager->remove($lesson);
        $this->entityManager->flush();
    }
}
