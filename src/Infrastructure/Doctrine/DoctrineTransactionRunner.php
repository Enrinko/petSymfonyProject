<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Shared\TransactionRunnerInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTransactionRunner implements TransactionRunnerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function inTransaction(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn (): mixed => $operation());
    }
}
