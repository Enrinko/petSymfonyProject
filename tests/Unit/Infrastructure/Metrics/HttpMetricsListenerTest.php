<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Metrics;

use App\Infrastructure\Metrics\HttpMetricsListener;
use App\Tests\Fake\FakeHttpKernel;
use App\Tests\Fake\FakeMetrics;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class HttpMetricsListenerTest extends TestCase
{
    private FakeMetrics $metrics;
    private HttpMetricsListener $listener;

    protected function setUp(): void
    {
        $this->metrics = new FakeMetrics();
        $this->listener = new HttpMetricsListener($this->metrics);
    }

    private function terminate(Request $request, Response $response): void
    {
        $this->listener->onTerminate(new TerminateEvent(new FakeHttpKernel(), $request, $response));
    }

    public function testCountsRequestWithRouteMethodStatus(): void
    {
        $request = Request::create('/healthz', 'GET', server: ['REQUEST_TIME_FLOAT' => microtime(true) - 0.05]);
        $request->attributes->set('_route', 'app_healthz');

        $this->terminate($request, new Response('', 200));

        self::assertCount(1, $this->metrics->increments);
        self::assertSame('http_requests_total', $this->metrics->increments[0]['name']);
        self::assertSame(
            ['route' => 'app_healthz', 'method' => 'GET', 'status' => '200'],
            $this->metrics->increments[0]['labels'],
        );

        self::assertCount(1, $this->metrics->observations);
        self::assertSame('http_request_duration_seconds', $this->metrics->observations[0]['name']);
        self::assertGreaterThan(0.0, $this->metrics->observations[0]['seconds']);
        // Без status: гистограмма и так «толстая» (бакеты × лейблы)
        self::assertSame(['route' => 'app_healthz', 'method' => 'GET'], $this->metrics->observations[0]['labels']);
    }

    public function testUnmatchedRouteGetsStableLabel(): void
    {
        $request = Request::create('/no-such-page', 'GET', server: ['REQUEST_TIME_FLOAT' => microtime(true)]);

        $this->terminate($request, new Response('', 404));

        self::assertSame('unmatched', $this->metrics->increments[0]['labels']['route']);
    }

    public function testMetricsRouteDoesNotCountItself(): void
    {
        $request = Request::create('/metrics', 'GET', server: ['REQUEST_TIME_FLOAT' => microtime(true)]);
        $request->attributes->set('_route', 'app_metrics');

        $this->terminate($request, new Response('', 200));

        self::assertSame([], $this->metrics->increments);
        self::assertSame([], $this->metrics->observations);
    }
}
