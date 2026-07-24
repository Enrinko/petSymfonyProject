<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * /metrics: изнутри сети (в тестах клиент ходит со 127.0.0.1 —
 * попадает под ips-правило) отдаёт text exposition format;
 * снаружи пускает только админа с сессией.
 */
final class MetricsEndpointTest extends FunctionalTestCase
{
    private const string EXTERNAL_IP = '203.0.113.10';

    public function testScrapeFromInternalNetworkNeedsNoAuth(): void
    {
        $this->client->request('GET', '/metrics');

        self::assertResponseIsSuccessful();
        // RenderTextFormat::MIME_TYPE не задаёт charset; Symfony Response
        // дописывает его сам через prepare() — регистр 'UTF-8' (не 'utf-8')
        self::assertResponseHeaderSame('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8');

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('app_users_total', $body);
        self::assertStringContainsString('app_clients_total', $body);
        // messenger_messages отсутствует в тестовой БД (не создаётся
        // миграциями) — gauge best-effort, тут не проверяем: см. докблок
        // MetricsController::collectGauges()
    }

    public function testHttpCountersAppearAfterTraffic(): void
    {
        // Kernel перезагружается между запросами — InMemory-хранилище
        // обнулилось бы; держим контейнер живым
        $this->client->disableReboot();

        $this->client->request('GET', '/healthz');
        $this->client->request('GET', '/metrics');

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('app_http_requests_total{method="GET",route="app_healthz",status="200"} 1', $body);
    }

    public function testExternalAnonymousIsRejected(): void
    {
        $this->client->request('GET', '/metrics', [], [], ['REMOTE_ADDR' => self::EXTERNAL_IP]);

        // Аноним снаружи — на логин (HTML entry point)
        self::assertResponseRedirects('/login');
    }

    public function testExternalAdminWithSessionIsAllowed(): void
    {
        $admin = $this->createUser(roles: ['ROLE_ADMIN']);
        $this->jsonRequest('POST', '/api/login', ['email' => $admin->getEmail(), 'password' => self::STRONG_PASSWORD]);

        $this->client->request('GET', '/metrics', [], [], ['REMOTE_ADDR' => self::EXTERNAL_IP]);

        self::assertResponseIsSuccessful();
    }

    public function testExternalPlainUserIsForbidden(): void
    {
        $user = $this->createUser();
        $this->jsonRequest('POST', '/api/login', ['email' => $user->getEmail(), 'password' => self::STRONG_PASSWORD]);

        $this->client->request('GET', '/metrics', [], [], ['REMOTE_ADDR' => self::EXTERNAL_IP]);

        self::assertResponseStatusCodeSame(403);
    }
}
