<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Metrics\MetricsInterface;

final class FakeMetrics implements MetricsInterface
{
    /** @var list<array{name: string, labels: array<string, string>}> */
    public array $increments = [];

    /** @var list<array{name: string, seconds: float, labels: array<string, string>}> */
    public array $observations = [];

    public function increment(string $name, array $labels = []): void
    {
        $this->increments[] = ['name' => $name, 'labels' => $labels];
    }

    public function observeDuration(string $name, float $seconds, array $labels = []): void
    {
        $this->observations[] = ['name' => $name, 'seconds' => $seconds, 'labels' => $labels];
    }
}
