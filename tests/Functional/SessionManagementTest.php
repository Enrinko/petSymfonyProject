<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\BrowserKit\Cookie;

/**
 * Активные сессии: учёт при входе, список с пометкой текущей,
 * дистанционное завершение (kill-list: жертва разлогинивается на своём
 * следующем запросе), «завершить все, кроме текущей», fully-барьер.
 */
final class SessionManagementTest extends FunctionalTestCase
{
    public function testLoginRegistersSessionAndListMarksCurrent(): void
    {
        $user = $this->createUser();

        $this->login($user->getEmail());

        $this->jsonRequest('GET', '/api/profile/sessions');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $sessions = $this->json()['sessions'];
        self::assertIsArray($sessions);
        self::assertCount(1, $sessions);
        self::assertIsArray($sessions[0]);
        self::assertTrue($sessions[0]['current']);
        self::assertNotNull($sessions[0]['ip']);
    }

    public function testTerminatingAnotherSessionLogsItOutOnNextRequest(): void
    {
        $user = $this->createUser();

        // Сессия-«жертва» (другое устройство)
        $this->login($user->getEmail());
        $victimCookie = $this->sessionCookie();

        // Вторая сессия — «текущая»
        $this->client->restart();
        $this->login($user->getEmail());

        // Видны обе; выбираем чужую
        $this->jsonRequest('GET', '/api/profile/sessions');
        $sessions = $this->json()['sessions'];
        self::assertCount(2, $sessions);
        $victim = array_values(array_filter($sessions, static fn (array $s): bool => !$s['current']))[0];

        // Завершаем её
        $this->jsonRequest('DELETE', '/api/profile/sessions/' . $victim['id']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Жертва делает следующий запрос — и разлогинена
        $this->client->restart();
        $this->client->getCookieJar()->set($victimCookie);
        $this->jsonRequest('GET', '/api/profile');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('на другом устройстве', (string) $this->json()['message']);
    }

    public function testCurrentSessionCannotBeTerminatedById(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());

        $this->jsonRequest('GET', '/api/profile/sessions');
        $current = $this->json()['sessions'][0];

        $this->jsonRequest('DELETE', '/api/profile/sessions/' . $current['id']);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    public function testTerminateAllOthersKeepsOnlyCurrent(): void
    {
        $user = $this->createUser();

        // Три сессии: две «чужие» + текущая
        $this->login($user->getEmail());
        $firstVictimCookie = $this->sessionCookie();
        $this->client->restart();
        $this->login($user->getEmail());
        $this->client->restart();
        $this->login($user->getEmail());

        $this->jsonRequest('DELETE', '/api/profile/sessions');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame(2, $this->json()['terminated']);

        $this->jsonRequest('GET', '/api/profile/sessions');
        $sessions = $this->json()['sessions'];
        self::assertCount(1, $sessions);
        self::assertTrue($sessions[0]['current']);

        // Первая «чужая» реально мертва
        $this->client->restart();
        $this->client->getCookieJar()->set($firstVictimCookie);
        $this->jsonRequest('GET', '/api/profile');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testSessionsApiRequiresFullAuthentication(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
            '_remember_me' => true,
        ]);

        // Остаёмся только с remember-кукой → REMEMBERED
        $jar = $this->client->getCookieJar();

        foreach ($jar->all() as $cookie) {
            if ($cookie->getName() !== 'REMEMBERME') {
                $jar->expire($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        $this->jsonRequest('GET', '/api/profile/sessions');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testPasswordChangeTerminatesOtherSessions(): void
    {
        $user = $this->createUser();

        // Чужая сессия + текущая
        $this->login($user->getEmail());
        $this->client->restart();
        $this->login($user->getEmail());

        $this->jsonRequest('POST', '/api/profile/password', [
            'currentPassword' => self::STRONG_PASSWORD,
            'newPassword' => 'Fresh!Passw0rd#77',
            'newPasswordConfirm' => 'Fresh!Passw0rd#77',
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame(1, $this->json()['terminatedSessions']);

        $this->jsonRequest('GET', '/api/profile/sessions');
        self::assertCount(1, $this->json()['sessions']);
    }

    private function login(string $email): void
    {
        $this->jsonRequest('POST', '/api/login', [
            'email' => $email,
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    private function sessionCookie(): Cookie
    {
        foreach ($this->client->getCookieJar()->all() as $cookie) {
            if ($cookie->getName() === 'MOCKSESSID' || str_contains($cookie->getName(), 'SESS')) {
                return $cookie;
            }
        }

        self::fail('Сессионная кука не найдена.');
    }
}
