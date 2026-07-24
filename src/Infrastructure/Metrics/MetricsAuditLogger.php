<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\Metrics\MetricsInterface;

/**
 * Декоратор порта аудита: каждый вызов AuditLoggerInterface::log() — плюс
 * один к app_audit_events_total{action}. Покрывает всё, что идёт через
 * этот порт (password.reset_requested, роли и т.д.), даром.
 *
 * Входы/выходы (login.succeeded/failed, logged_out) в него НЕ попадают —
 * SecurityEventsAuditSubscriber пишет их напрямую через
 * AuditEventRepositoryInterface и считает тот же счётчик сам.
 */
final readonly class MetricsAuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private AuditLoggerInterface $inner,
        private MetricsInterface $metrics,
    ) {
    }

    public function log(
        AuditAction $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $payload = [],
    ): void {
        $this->inner->log($action, $subjectType, $subjectId, $payload);
        $this->metrics->increment('audit_events_total', ['action' => $action->value]);
    }
}
