<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * /metrics: авторизацию решает MetricsController, не IP. Скрейпер ходит по
 * Bearer-токену (METRICS_TOKEN, в тестах — из .env.test), человек — админом
 * с полной сессией. Всё прочее (аноним, чужой токен, обычный юзер) — 403.
 */
final class MetricsEndpointTest extends FunctionalTestCase
{
    private const string TOKEN = 'test-metrics-token';

    /**
     * @param array<string, string> $extraServer
     */
    private function getMetrics(array $extraServer = []): void
    {
        $this->client->request('GET', '/metrics', [], [], $extraServer);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
    }

    public function testScrapeWithValidTokenNeedsNoSession(): void
    {
        $this->getMetrics($this->bearer(self::TOKEN));

        self::assertResponseIsSuccessful();
        // RenderTextFormat::MIME_TYPE не задаёт charset; Symfony Response
        // дописывает его сам через prepare() — регистр 'UTF-8' (не 'utf-8')
        self::assertResponseHeaderSame('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8');

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('app_users_total', $body);
        self::assertStringContainsString('app_clients_total', $body);
    }

    public function testHttpCountersAppearAfterTraffic(): void
    {
        // Kernel перезагружается между запросами — InMemory-хранилище
        // обнулилось бы; держим контейнер живым
        $this->client->disableReboot();

        $this->client->request('GET', '/healthz');
        $this->getMetrics($this->bearer(self::TOKEN));

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('app_http_requests_total{method="GET",route="app_healthz",status="200"} 1', $body);
    }

    public function testAnonymousWithoutTokenIsForbidden(): void
    {
        $this->getMetrics();

        self::assertResponseStatusCodeSame(403);
    }

    public function testWrongTokenIsForbidden(): void
    {
        $this->getMetrics($this->bearer('nope-wrong-token'));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminWithSessionIsAllowed(): void
    {
        $admin = $this->createUser(roles: ['ROLE_ADMIN']);
        $this->jsonRequest('POST', '/api/login', ['email' => $admin->getEmail(), 'password' => self::STRONG_PASSWORD]);

        $this->getMetrics();

        self::assertResponseIsSuccessful();
    }

    public function testPlainUserWithSessionIsForbidden(): void
    {
        $user = $this->createUser();
        $this->jsonRequest('POST', '/api/login', ['email' => $user->getEmail(), 'password' => self::STRONG_PASSWORD]);

        $this->getMetrics();

        self::assertResponseStatusCodeSame(403);
    }
}
