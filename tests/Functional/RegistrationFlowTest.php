<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Регистрация сквозь реальный контейнер: валидация, дубли, лимитер по IP
 * и вход свежесозданным аккаунтом.
 */
final class RegistrationFlowTest extends FunctionalTestCase
{
    public function testRegisterThenLogin(): void
    {
        $email = sprintf('fresh-%s@example.test', uniqid());

        $this->jsonRequest('POST', '/api/register', [
            'email' => $email,
            'password' => self::STRONG_PASSWORD, 'passwordConfirm' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        $this->jsonRequest('POST', '/api/login', [
            'email' => $email,
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testDuplicateEmailRejected(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/register', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD, 'passwordConfirm' => self::STRONG_PASSWORD,
        ]);

        // 409 Conflict — дубль это не ошибка формата, а конфликт состояния
        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertIsArray($body['errors'] ?? null);
        self::assertArrayHasKey('email', $body['errors']);
    }

    public function testWeakPasswordRejected(): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'email' => sprintf('weak-%s@example.test', uniqid()),
            'password' => 'short',
            'passwordConfirm' => 'short',
        ]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertIsArray($body['errors'] ?? null);
        self::assertArrayHasKey('password', $body['errors']);
    }

    public function testRegistrationIsRateLimitedPerIp(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->jsonRequest('POST', '/api/register', [
                'email' => sprintf('limited-%d-%s@example.test', $i, uniqid()),
                'password' => self::STRONG_PASSWORD, 'passwordConfirm' => self::STRONG_PASSWORD,
            ]);
            self::assertSame(201, $this->client->getResponse()->getStatusCode(), sprintf('Регистрация %d.', $i + 1));
        }

        $this->jsonRequest('POST', '/api/register', [
            'email' => sprintf('limited-final-%s@example.test', uniqid()),
            'password' => self::STRONG_PASSWORD, 'passwordConfirm' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(429, $this->client->getResponse()->getStatusCode());
    }
}
