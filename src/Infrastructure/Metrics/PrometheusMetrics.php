<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use App\Domain\Metrics\MetricsInterface;
use Prometheus\CollectorRegistry;
use Psr\Log\LoggerInterface;

/**
 * Адаптер порта метрик на promphp. Namespace 'app' даёт имена вида
 * app_http_requests_total. Любая ошибка хранилища (Redis упал) глотается:
 * метрики — наблюдатель, а не участник запроса.
 */
final readonly class PrometheusMetrics implements MetricsInterface
{
    /** Бакеты латентности: 5ms…10s — стандартный веб-диапазон */
    private const array BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    private const array HELP = [
        'http_requests_total' => 'Total HTTP requests by route, method and status.',
        'http_request_duration_seconds' => 'HTTP request duration in seconds.',
        'audit_events_total' => 'Security audit events by action.',
        'users_registered_total' => 'Total user registrations.',
    ];

    public function __construct(
        private CollectorRegistry $registry,
        private LoggerInterface $logger,
    ) {
    }

    public function increment(string $name, array $labels = []): void
    {
        // Порядок лейблов фиксируется при первой регистрации метрики, а дальнейшие
        // вызовы передают значения позиционно (promphp сверяет только количество) —
        // сортируем ключи, чтобы разный порядок вызова не перепутал значения местами.
        ksort($labels);

        try {
            $counter = $this->registry->getOrRegisterCounter('app', $name, self::HELP[$name] ?? $name, array_keys($labels));
            $counter->incBy(1, array_values($labels));
        } catch (\Throwable $e) {
            $this->logger->error('Metrics increment failed.', ['exception' => $e, 'metric' => $name]);
        }
    }

    public function observeDuration(string $name, float $seconds, array $labels = []): void
    {
        // См. комментарий в increment(): нормализуем порядок лейблов, чтобы имена
        // и позиционные значения всегда соответствовали друг другу.
        ksort($labels);

        try {
            $histogram = $this->registry->getOrRegisterHistogram('app', $name, self::HELP[$name] ?? $name, array_keys($labels), self::BUCKETS);
            $histogram->observe($seconds, array_values($labels));
        } catch (\Throwable $e) {
            $this->logger->error('Metrics observe failed.', ['exception' => $e, 'metric' => $name]);
        }
    }
}
