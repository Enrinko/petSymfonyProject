<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * json_login: успех даёт рабочую сессию, ошибка — JSON-конверт,
 * перебор паролей упирается в login_throttling.
 */
final class JsonLoginTest extends FunctionalTestCase
{
    public function testSuccessfulLoginEstablishesSession(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Сессия реально работает: защищённая страница доступна без нового логина
        $this->client->request('GET', '/clients');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testWrongPasswordGives401JsonEnvelope(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => 'definitely-wrong-password',
        ]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertArrayHasKey('message', $this->json());
    }

    public function testLoginThrottlingKicksInAfterFiveAttempts(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 5; ++$i) {
            $this->jsonRequest('POST', '/api/login', [
                'email' => $user->getEmail(),
                'password' => 'definitely-wrong-password',
            ]);
            self::assertSame(401, $this->client->getResponse()->getStatusCode(), sprintf('Попытка %d.', $i + 1));
        }

        // Шестая попытка — даже с ВЕРНЫМ паролем — отклоняется троттлингом
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(429, $this->client->getResponse()->getStatusCode());
    }

    public function testLogoutEndsSession(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/logout');
        self::assertTrue($this->client->getResponse()->isRedirection());

        $this->client->request('GET', '/clients');
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
    }
}
