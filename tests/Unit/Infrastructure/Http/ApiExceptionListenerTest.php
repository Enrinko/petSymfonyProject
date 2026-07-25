<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\ApiExceptionListener;
use App\Infrastructure\Http\LocaleResolver;
use App\Infrastructure\Logging\RequestIdProvider;
use App\Tests\Fake\CatalogueTranslatorFactory;
use App\Tests\Fake\FakeHttpKernel;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionListenerTest extends TestCase
{
    public function testHttpExceptionOnApiPathBecomesJsonEnvelope(): void
    {
        $event = $this->createEvent('/api/clients/42', new NotFoundHttpException('No route'));

        $this->listener(false)($event);

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

        $this->listener(false)($event);

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

        $this->listener(false)($event);

        self::assertNull($event->getResponse());
    }

    public function testUnexpectedExceptionBecomesGeneric500InProd(): void
    {
        $event = $this->createEvent('/api/clients', new \RuntimeException('DB down: secret dsn'));

        $this->listener(false)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        // Сообщение включает request id — «код обращения» для поддержки
        self::assertStringStartsWith('Внутренняя ошибка сервера.', $payload['message']);
        self::assertMatchesRegularExpression('/Код обращения: [0-9a-f]{16}/u', $payload['message']);
        self::assertNull($payload['errors']);
        self::assertStringNotContainsString('secret', (string) $response->getContent());
    }

    public function testUnexpectedExceptionIsNotInterceptedInDebug(): void
    {
        $event = $this->createEvent('/api/clients', new \RuntimeException('boom'));

        $this->listener(true)($event);

        self::assertNull($event->getResponse(), 'В debug-режиме 500 отдаёт штатная страница ошибки с трейсом');
    }

    public function testMessageIsLocalisedToEnglish(): void
    {
        $event = $this->createEvent('/api/clients/42', new NotFoundHttpException('No route'), 'en');

        $this->listener(false)($event);

        self::assertSame(
            'Not found.',
            json_decode((string) $event->getResponse()?->getContent(), true)['message'],
        );
    }

    private function listener(bool $debug): ApiExceptionListener
    {
        return new ApiExceptionListener(
            $debug,
            new RequestIdProvider(),
            new NullLogger(),
            CatalogueTranslatorFactory::create(),
            new LocaleResolver(new TokenStorage()),
        );
    }

    private function createEvent(string $path, \Throwable $exception, string $locale = 'ru'): ExceptionEvent
    {
        $request = Request::create($path);
        // Локаль резолвится через LocaleResolver: гость без сессии → Accept-Language
        $request->headers->set('Accept-Language', $locale);

        return new ExceptionEvent(
            new FakeHttpKernel(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
