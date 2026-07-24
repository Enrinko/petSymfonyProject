<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\User\BackupCode;
use App\Domain\User\BackupCodeRepositoryInterface;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineBackupCodeRepository implements BackupCodeRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findActiveByUser(User $user): array
    {
        /** @var list<BackupCode> */
        return $this->em->createQueryBuilder()
            ->select('c')
            ->from(BackupCode::class, 'c')
            ->where('c.user = :user')
            ->andWhere('c.usedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function save(BackupCode $code): void
    {
        $this->em->persist($code);
        $this->em->flush();
    }

    public function removeAllForUser(User $user): void
    {
        $this->em->createQueryBuilder()
            ->delete(BackupCode::class, 'c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
