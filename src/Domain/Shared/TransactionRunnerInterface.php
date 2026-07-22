<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Порт атомарного выполнения нескольких операций с хранилищем.
 * Реализация — за инфраструктурой (Doctrine), Application-слой про Doctrine не знает.
 */
interface TransactionRunnerInterface
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function inTransaction(callable $operation): mixed;
}
