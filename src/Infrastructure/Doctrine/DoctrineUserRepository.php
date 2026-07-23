<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function findById(int $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findPage(int $page, int $perPage, string $search = ''): array
    {
        return $this->createSearchQueryBuilder($search)
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countBySearch(string $search = ''): int
    {
        return (int) $this->createSearchQueryBuilder($search)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(User $user): void
    {
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Гонка с pre-check в хендлере: конкурентная вставка того же email.
            throw new EmailAlreadyInUseException(sprintf('Email "%s" is already registered.', $user->getEmail()));
        }
    }

    private function createSearchQueryBuilder(string $search): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u');

        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(u.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $queryBuilder;
    }
}
