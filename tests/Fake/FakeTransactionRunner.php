<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Shared\TransactionRunnerInterface;

final class FakeTransactionRunner implements TransactionRunnerInterface
{
    public int $transactions = 0;

    public function inTransaction(callable $operation): mixed
    {
        ++$this->transactions;

        return $operation();
    }
}
