<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Prometheus\MetricFamilySamples;
use Prometheus\Storage\Adapter;

/**
 * Хранилище promphp, имитирующее сбой (например, недоступный Redis).
 * Нужно, чтобы проверить обещание PrometheusMetrics: ошибка хранилища
 * логируется и не пробрасывается наружу.
 */
final class ThrowingStorageAdapter implements Adapter
{
    /**
     * @return MetricFamilySamples[]
     */
    public function collect(): array
    {
        throw new \RuntimeException('Storage unavailable.');
    }

    /**
     * @param mixed[] $data
     */
    public function updateSummary(array $data): void
    {
        throw new \RuntimeException('Storage unavailable.');
    }

    /**
     * @param mixed[] $data
     */
    public function updateHistogram(array $data): void
    {
        throw new \RuntimeException('Storage unavailable.');
    }

    /**
     * @param mixed[] $data
     */
    public function updateGauge(array $data): void
    {
        throw new \RuntimeException('Storage unavailable.');
    }

    /**
     * @param mixed[] $data
     */
    public function updateCounter(array $data): void
    {
        throw new \RuntimeException('Storage unavailable.');
    }

    public function wipeStorage(): void
    {
        throw new \RuntimeException('Storage unavailable.');
    }
}
