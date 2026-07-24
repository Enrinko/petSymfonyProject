<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Metrics;

use App\Infrastructure\Metrics\PrometheusMetrics;
use App\Tests\Fake\SpyLogger;
use App\Tests\Fake\ThrowingStorageAdapter;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

final class PrometheusMetricsTest extends TestCase
{
    private CollectorRegistry $registry;
    private PrometheusMetrics $metrics;

    protected function setUp(): void
    {
        $this->registry = new CollectorRegistry(new InMemory(), false);
        $this->metrics = new PrometheusMetrics($this->registry, new NullLogger());
    }

    public function testIncrementRendersCounterWithLabels(): void
    {
        $this->metrics->increment('http_requests_total', ['route' => 'app_healthz', 'method' => 'GET', 'status' => '200']);
        $this->metrics->increment('http_requests_total', ['route' => 'app_healthz', 'method' => 'GET', 'status' => '200']);

        $text = (new RenderTextFormat())->render($this->registry->getMetricFamilySamples());

        // Лейблы нормализуются через ksort() — порядок в выводе всегда алфавитный,
        // независимо от того, в каком порядке ключи передали в вызов.
        self::assertStringContainsString('app_http_requests_total{method="GET",route="app_healthz",status="200"} 2', $text);
    }

    public function testObserveDurationRendersHistogramBuckets(): void
    {
        $this->metrics->observeDuration('http_request_duration_seconds', 0.042, ['route' => 'app_healthz', 'method' => 'GET']);

        $text = (new RenderTextFormat())->render($this->registry->getMetricFamilySamples());

        self::assertStringContainsString('app_http_request_duration_seconds_bucket', $text);
        self::assertStringContainsString('le="0.05"', $text);
        // Лейблы нормализуются через ksort() — "method" перед "route" в алфавитном порядке.
        self::assertStringContainsString('app_http_request_duration_seconds_count{method="GET",route="app_healthz"} 1', $text);
    }

    public function testIncrementSwallowsStorageFailureAndLogsError(): void
    {
        $logger = new SpyLogger();
        $registry = new CollectorRegistry(new ThrowingStorageAdapter(), false);
        $metrics = new PrometheusMetrics($registry, $logger);

        // Промис адаптера: сбой хранилища (например, недоступный Redis) не должен
        // ронять запрос — increment() обязан молча проглотить исключение и залогировать его.
        $metrics->increment('http_requests_total', ['route' => 'app_healthz', 'method' => 'GET', 'status' => '500']);

        self::assertSame(LogLevel::ERROR, $logger->lastLevel());
        self::assertSame('Metrics increment failed.', $logger->lastMessage());
    }

    public function testObserveDurationSwallowsStorageFailureAndLogsError(): void
    {
        $logger = new SpyLogger();
        $registry = new CollectorRegistry(new ThrowingStorageAdapter(), false);
        $metrics = new PrometheusMetrics($registry, $logger);

        $metrics->observeDuration('http_request_duration_seconds', 0.042, ['route' => 'app_healthz', 'method' => 'GET']);

        self::assertSame(LogLevel::ERROR, $logger->lastLevel());
        self::assertSame('Metrics observe failed.', $logger->lastMessage());
    }
}
