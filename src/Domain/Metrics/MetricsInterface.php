<?php

declare(strict_types=1);

namespace App\Domain\Metrics;

/**
 * Порт метрик приложения. Узкий по умыслу: счётчик и длительность —
 * всё, что нужно домену. Ошибки записи адаптер глотает сам:
 * метрики — best effort, наблюдаемость не важнее самой операции.
 */
interface MetricsInterface
{
    /** @param array<string, string> $labels */
    public function increment(string $name, array $labels = []): void;

    /** @param array<string, string> $labels */
    public function observeDuration(string $name, float $seconds, array $labels = []): void;
}
