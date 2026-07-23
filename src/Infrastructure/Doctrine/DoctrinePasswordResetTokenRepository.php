<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\PasswordReset\PasswordResetToken;
use App\Domain\PasswordReset\PasswordResetTokenRepositoryInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findValidByHash(string $tokenHash, \DateTimeImmutable $now): ?PasswordResetToken
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(PasswordResetToken::class, 't')
            ->andWhere('t.tokenHash = :hash')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteForUser(User $user): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(PasswordResetToken::class, 't')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function deleteExpired(\DateTimeImmutable $now): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->delete(PasswordResetToken::class, 't')
            ->andWhere('t.expiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    public function save(PasswordResetToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    public function remove(PasswordResetToken $token): void
    {
        $this->entityManager->remove($token);
        $this->entityManager->flush();
    }
}
