<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\Role;

/**
 * Карта доступа: самый дешёвый способ заметить, что маршрут случайно
 * открыт анониму или закрыт не тем статусом (302 HTML vs 401 JSON).
 */
final class SecurityMapTest extends FunctionalTestCase
{
    public function testAnonymousHtmlPagesRedirectToLogin(): void
    {
        foreach (['/clients', '/schedule', '/events', '/admin/users'] as $path) {
            $this->client->request('GET', $path);

            $response = $this->client->getResponse();
            self::assertSame(302, $response->getStatusCode(), sprintf('%s: ожидали 302.', $path));
            self::assertStringContainsString('/login', (string) $response->headers->get('Location'), $path);
        }
    }

    public function testAnonymousApiGets401JsonEnvelope(): void
    {
        foreach (['/api/clients', '/api/admin/users', '/api/dashboard'] as $path) {
            $this->jsonRequest('GET', $path);

            $response = $this->client->getResponse();
            self::assertSame(401, $response->getStatusCode(), sprintf('%s: ожидали 401.', $path));

            $body = $this->json();
            self::assertArrayHasKey('message', $body, $path);
        }
    }

    public function testPublicPagesAreAccessible(): void
    {
        foreach (['/', '/login', '/register', '/forgot-password', '/healthz'] as $path) {
            $this->client->request('GET', $path);

            self::assertSame(
                200,
                $this->client->getResponse()->getStatusCode(),
                sprintf('%s: публичная страница должна отдавать 200.', $path),
            );
        }
    }

    public function testPlainUserCannotAccessAdmin(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/admin/users');
        self::assertSame(403, $this->client->getResponse()->getStatusCode());

        $this->jsonRequest('GET', '/api/admin/users');
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertArrayHasKey('message', $this->json());
    }

    public function testPlainUserCanAccessCrmPages(): void
    {
        $this->client->loginUser($this->createUser());

        foreach (['/clients', '/schedule'] as $path) {
            $this->client->request('GET', $path);
            self::assertSame(200, $this->client->getResponse()->getStatusCode(), $path);
        }
    }

    public function testAdminCanAccessAdmin(): void
    {
        $this->client->loginUser($this->createUser(roles: [Role::Admin->value]));

        $this->client->request('GET', '/admin/users');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->jsonRequest('GET', '/api/admin/users');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $body = $this->json();
        self::assertArrayHasKey('users', $body);
        self::assertArrayHasKey('total', $body);
    }
}
