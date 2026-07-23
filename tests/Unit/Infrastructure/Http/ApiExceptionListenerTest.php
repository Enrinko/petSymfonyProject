<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\ApiExceptionListener;
use App\Tests\Fake\FakeHttpKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionListenerTest extends TestCase
{
    public function testHttpExceptionOnApiPathBecomesJsonEnvelope(): void
    {
        $event = $this->createEvent('/api/clients/42', new NotFoundHttpException('No route'));

        (new ApiExceptionListener(false))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Не найдено.', 'errors' => null],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testHttpExceptionHeadersArePreserved(): void
    {
        $event = $this->createEvent('/api/login', new TooManyRequestsHttpException(60));

        (new ApiExceptionListener(false))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('60', $response->headers->get('Retry-After'));
        self::assertSame(
            'Слишком много запросов. Попробуйте позже.',
            json_decode((string) $response->getContent(), true)['message'],
        );
    }

    public function testNonApiPathIsIgnored(): void
    {
        $event = $this->createEvent('/admin/users', new NotFoundHttpException());

        (new ApiExceptionListener(false))($event);

        self::assertNull($event->getResponse());
    }

    public function testUnexpectedExceptionBecomesGeneric500InProd(): void
    {
        $event = $this->createEvent('/api/clients', new \RuntimeException('DB down: secret dsn'));

        (new ApiExceptionListener(false))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        self::assertSame(['message' => 'Внутренняя ошибка сервера.', 'errors' => null], $payload);
        self::assertStringNotContainsString('secret', (string) $response->getContent());
    }

    public function testUnexpectedExceptionIsNotInterceptedInDebug(): void
    {
        $event = $this->createEvent('/api/clients', new \RuntimeException('boom'));

        (new ApiExceptionListener(true))($event);

        self::assertNull($event->getResponse(), 'В debug-режиме 500 отдаёт штатная страница ошибки с трейсом');
    }

    private function createEvent(string $path, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            new FakeHttpKernel(),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
