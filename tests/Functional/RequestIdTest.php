<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Сквозной request id: свой в каждом ответе, чужой (валидный) уважается,
 * мусорный отбрасывается.
 */
final class RequestIdTest extends FunctionalTestCase
{
    public function testResponseCarriesGeneratedRequestId(): void
    {
        $this->client->request('GET', '/healthz');

        $id = $this->client->getResponse()->headers->get('X-Request-Id');
        self::assertNotNull($id);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id);
    }

    public function testInboundRequestIdIsEchoed(): void
    {
        $this->client->request('GET', '/healthz', [], [], [
            'HTTP_X_REQUEST_ID' => 'proxy-42-abcDEF',
        ]);

        self::assertSame('proxy-42-abcDEF', $this->client->getResponse()->headers->get('X-Request-Id'));
    }

    public function testMalformedInboundRequestIdIsReplaced(): void
    {
        $this->client->request('GET', '/healthz', [], [], [
            'HTTP_X_REQUEST_ID' => 'bad id with spaces!',
        ]);

        $id = $this->client->getResponse()->headers->get('X-Request-Id');
        self::assertNotNull($id);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id);
    }

    public function testApiErrorsCarryRequestIdToo(): void
    {
        $this->jsonRequest('GET', '/api/clients'); // аноним → 401

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertNotNull($this->client->getResponse()->headers->get('X-Request-Id'));
    }
}
