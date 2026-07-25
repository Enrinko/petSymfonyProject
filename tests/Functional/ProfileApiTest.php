<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Профиль: чтение/имя/смена пароля. Смена пароля требует ПОЛНОЙ
 * аутентификации (remember-куки мало) и верного текущего пароля.
 */
final class ProfileApiTest extends FunctionalTestCase
{
    public function testAnonymousGets401(): void
    {
        $this->jsonRequest('GET', '/api/profile');

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testReadProfile(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->jsonRequest('GET', '/api/profile');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertSame($user->getEmail(), $body['email']);
        self::assertNull($body['displayName']);
        self::assertNull($body['avatarUrl']);
        self::assertIsArray($body['roles']);
    }

    public function testUpdateDisplayName(): void
    {
        $this->client->loginUser($this->createUser());

        $this->jsonRequest('PATCH', '/api/profile', ['displayName' => '  Анна Скрипичная ']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('Анна Скрипичная', $this->json()['displayName']);

        // Пустая строка убирает имя
        $this->jsonRequest('PATCH', '/api/profile', ['displayName' => '']);
        self::assertNull($this->json()['displayName']);
    }

    public function testUpdateLocalePersistsAndKeepsDisplayName(): void
    {
        $this->client->loginUser($this->createUser());

        $this->jsonRequest('PATCH', '/api/profile', ['displayName' => 'Анна']);
        self::assertSame('Анна', $this->json()['displayName']);

        // Точечный PATCH: locale меняется, не присланное displayName не затирается
        $this->jsonRequest('PATCH', '/api/profile', ['locale' => 'en']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertSame('en', $body['locale']);
        self::assertSame('Анна', $body['displayName']);
    }

    public function testUnsupportedLocaleRejected(): void
    {
        $this->client->loginUser($this->createUser());

        $this->jsonRequest('PATCH', '/api/profile', ['locale' => 'de']);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertIsArray($body['errors'] ?? null);
        self::assertArrayHasKey('locale', $body['errors']);
    }

    public function testTooLongDisplayNameRejected(): void
    {
        $this->client->loginUser($this->createUser());

        $this->jsonRequest('PATCH', '/api/profile', ['displayName' => str_repeat('а', 81)]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertIsArray($body['errors'] ?? null);
        self::assertArrayHasKey('displayName', $body['errors']);
    }

    public function testChangePasswordHappyPath(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $newPassword = 'Fresh!Passw0rd#77';

        $this->jsonRequest('POST', '/api/profile/password', [
            'currentPassword' => self::STRONG_PASSWORD,
            'newPassword' => $newPassword,
            'newPasswordConfirm' => $newPassword,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Новый пароль реально работает
        $this->client->restart();
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => $newPassword,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testChangePasswordWithWrongCurrentIs422(): void
    {
        $this->client->loginUser($this->createUser());

        $this->jsonRequest('POST', '/api/profile/password', [
            'currentPassword' => 'totally-wrong',
            'newPassword' => 'Fresh!Passw0rd#77',
            'newPasswordConfirm' => 'Fresh!Passw0rd#77',
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertIsArray($body['errors'] ?? null);
        self::assertArrayHasKey('currentPassword', $body['errors']);
    }

    public function testChangePasswordRequiresFullAuthentication(): void
    {
        $user = $this->createUser();

        // Входим с remember-me и роняем сессию: остаёмся REMEMBERED
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
            '_remember_me' => true,
        ]);
        $jar = $this->client->getCookieJar();

        foreach ($jar->all() as $cookie) {
            if ($cookie->getName() !== 'REMEMBERME') {
                $jar->expire($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        // Профиль читается (обычная страница)…
        $this->jsonRequest('GET', '/api/profile');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // …но смена пароля из remember-куки запрещена
        $this->jsonRequest('POST', '/api/profile/password', [
            'currentPassword' => self::STRONG_PASSWORD,
            'newPassword' => 'Fresh!Passw0rd#77',
            'newPasswordConfirm' => 'Fresh!Passw0rd#77',
        ]);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testProfilePageRenders(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/profile');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testHomePageGreetsByDisplayName(): void
    {
        $this->client->loginUser($this->createUser());
        $this->jsonRequest('PATCH', '/api/profile', ['displayName' => 'Энрико Тестовый']);

        $this->client->request('GET', '/');

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString(
            'Здравствуйте, Энрико Тестовый!',
            $html,
            'Приветствие на главной обязано использовать отображаемое имя.',
        );
    }

    public function testHomePageFallsBackToEmailLocalPart(): void
    {
        $user = $this->createUser('melody.maker@example.test');
        $this->client->loginUser($user);

        $this->client->request('GET', '/');

        self::assertStringContainsString(
            'Здравствуйте, melody.maker!',
            (string) $this->client->getResponse()->getContent(),
        );
    }
}
