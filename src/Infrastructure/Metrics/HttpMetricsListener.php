<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use App\Domain\Metrics\MetricsInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * HTTP-метрики на kernel.terminate: ответ уже отправлен,
 * запись в Redis не добавляет латентности пользователю.
 * Лейбл route — имя роута (не URL): кардинальность ограничена роутером.
 */
final readonly class HttpMetricsListener
{
    public function __construct(
        private MetricsInterface $metrics,
    ) {
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $route = \is_string($route) ? $route : 'unmatched';

        // Скрейп Prometheus'а сам по себе — не трафик приложения
        if ($route === 'app_metrics') {
            return;
        }

        $method = $request->getMethod();

        $this->metrics->increment('http_requests_total', [
            'route' => $route,
            'method' => $method,
            'status' => (string) $event->getResponse()->getStatusCode(),
        ]);

        $start = $request->server->get('REQUEST_TIME_FLOAT');

        if (is_numeric($start)) {
            $this->metrics->observeDuration(
                'http_request_duration_seconds',
                max(0.0, microtime(true) - (float) $start),
                ['route' => $route, 'method' => $method],
            );
        }
    }
}
