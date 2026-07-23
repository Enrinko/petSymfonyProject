<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Session\UserSession;
use App\Domain\Session\UserSessionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserSessionRepository implements UserSessionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(UserSession $session): void
    {
        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }

    public function findByHash(string $sessionIdHash): ?UserSession
    {
        return $this->entityManager->getRepository(UserSession::class)
            ->findOneBy(['sessionIdHash' => $sessionIdHash]);
    }

    public function findByUser(int $userId): array
    {
        return $this->entityManager->getRepository(UserSession::class)
            ->findBy(['userId' => $userId], ['lastSeenAt' => 'DESC', 'id' => 'DESC']);
    }

    public function removeByHash(string $sessionIdHash): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(UserSession::class, 's')
            ->where('s.sessionIdHash = :hash')
            ->setParameter('hash', $sessionIdHash)
            ->getQuery()
            ->execute();
    }

    public function removeAllForUserExcept(int $userId, string $keepSessionIdHash): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(UserSession::class, 's')
            ->where('s.userId = :user')
            ->andWhere('s.sessionIdHash != :keep')
            ->setParameter('user', $userId)
            ->setParameter('keep', $keepSessionIdHash)
            ->getQuery()
            ->execute();
    }

    public function removeStale(\DateTimeImmutable $threshold): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(UserSession::class, 's')
            ->where('s.lastSeenAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
