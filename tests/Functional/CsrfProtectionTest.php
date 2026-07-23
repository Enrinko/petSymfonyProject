<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\Role;

/**
 * CSRF-защита изменяющих /api-запросов: свой клиент маркируется заголовком
 * X-Requested-With (HTML-форма его поставить не может), Origin и
 * Sec-Fetch-Site сверяются при наличии — защита в глубину поверх SameSite.
 */
final class CsrfProtectionTest extends FunctionalTestCase
{
    /**
     * @param array<string, string> $extraServer
     * @param array<string, mixed>  $payload
     */
    private function rawRequest(
        string $method,
        string $uri,
        array $payload = [],
        array $extraServer = [],
        string $contentType = 'application/json',
    ): void {
        $this->client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => $contentType,
            ...$extraServer,
        ], $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function registerPayload(): array
    {
        return [
            'email' => sprintf('csrf-%s@example.test', uniqid()),
            'password' => self::STRONG_PASSWORD,
            'passwordConfirm' => self::STRONG_PASSWORD,
        ];
    }

    public function testMutatingApiWithoutMarkerHeaderIs403(): void
    {
        $this->rawRequest('POST', '/api/register', $this->registerPayload());

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertArrayHasKey('message', $this->json());
    }

    public function testHtmlFormEmulationIs403(): void
    {
        // Трюк с <form enctype="text/plain"> — единственный способ формы
        // отправить «почти JSON»; маркер-заголовок она поставить не может
        $this->rawRequest('POST', '/api/register', $this->registerPayload(), contentType: 'text/plain');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testCrossOriginIs403(): void
    {
        $this->rawRequest('POST', '/api/register', $this->registerPayload(), [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ORIGIN' => 'https://evil.example',
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testCrossSiteFetchMetadataIs403(): void
    {
        $this->rawRequest('POST', '/api/register', $this->registerPayload(), [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_SEC_FETCH_SITE' => 'cross-site',
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginEndpointIsProtectedToo(): void
    {
        // Login CSRF: атакующий «логинит» жертву в свой аккаунт
        $this->rawRequest('POST', '/api/login', ['email' => 'x@example.test', 'password' => 'irrelevant']);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminRolePatchWithoutMarkerIs403(): void
    {
        // Ключевой риск из спеки: CSRF на сессию администратора = повышение привилегий
        $target = $this->createUser();
        $this->client->loginUser($this->createUser(roles: [Role::Admin->value]));

        $this->rawRequest(
            'PATCH',
            sprintf('/api/admin/users/%d/roles', $target->getId()),
            ['roles' => [Role::User->value, Role::Admin->value]],
        );

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testLegitJsonRequestPasses(): void
    {
        // Штатный запрос своего клиента: маркер есть, Origin браузер может не слать
        $this->jsonRequest('POST', '/api/register', $this->registerPayload());

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
    }

    public function testSameOriginHeadersPass(): void
    {
        $this->rawRequest('POST', '/api/register', $this->registerPayload(), [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ORIGIN' => 'http://localhost',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
        ]);

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
    }

    public function testSafeMethodsAreNotBlocked(): void
    {
        $this->client->loginUser($this->createUser());

        // GET без маркера — читающие запросы CSRF-защита не трогает
        $this->client->request('GET', '/api/clients');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }
}
