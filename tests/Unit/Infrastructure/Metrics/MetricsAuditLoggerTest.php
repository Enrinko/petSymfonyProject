<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Metrics;

use App\Domain\Audit\AuditAction;
use App\Infrastructure\Metrics\MetricsAuditLogger;
use App\Tests\Fake\FakeMetrics;
use App\Tests\Fake\SpyAuditLogger;
use PHPUnit\Framework\TestCase;

final class MetricsAuditLoggerTest extends TestCase
{
    public function testDelegatesAndCounts(): void
    {
        $inner = new SpyAuditLogger();
        $metrics = new FakeMetrics();
        $logger = new MetricsAuditLogger($inner, $metrics);

        $logger->log(AuditAction::LoginFailed, 'user', '7', ['email' => 'a@b.c']);

        // Запись дошла до настоящего журнала без искажений
        self::assertSame(AuditAction::LoginFailed, $inner->lastAction());
        self::assertSame('7', $inner->entries[0]['subjectId']);

        // И посчиталась в метрику
        self::assertSame(
            [['name' => 'audit_events_total', 'labels' => ['action' => 'login.failed']]],
            $metrics->increments,
        );
    }
}
