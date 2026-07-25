<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * База функциональных тестов: реальное ядро, реальный роутер и firewall,
 * реальная тестовая БД (каждый тест в транзакции с откатом — DAMA).
 */
abstract class FunctionalTestCase extends WebTestCase
{
    /** Валиден по парольной политике: длина ≥ 10 + PasswordStrength. */
    protected const string STRONG_PASSWORD = 'S3cure!Passw0rd#42';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // BrowserKit по умолчанию шлёт Accept-Language: en-us — с появлением i18n
        // это переключало бы страницы на английский. Тесты ходят «русским браузером»;
        // английский включают явно (см. LocaleSwitchTest).
        $this->client = static::createClient([], ['HTTP_ACCEPT_LANGUAGE' => 'ru']);

        // Счётчики rate limiter'ов живут в файловом пуле и переживают
        // и тесты, и прогоны — чистим, чтобы лимиты каждого теста были свои
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    /**
     * @param list<string> $roles дополнительные роли (ROLE_USER есть всегда)
     */
    protected function createUser(
        ?string $email = null,
        string $password = self::STRONG_PASSWORD,
        array $roles = [],
    ): User {
        // bcrypt напрямую: алиас хэшера заинлайнен и недоступен из test-контейнера,
        // а 'auto'-хэшер валидирует bcrypt-хэши по префиксу. cost 4 — как в when@test.
        $user = User::register(
            $email ?? sprintf('user-%s@example.test', uniqid()),
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
        );

        if ($roles !== []) {
            $user->changeRoles($roles);
        }

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function jsonRequest(string $method, string $uri, array $payload = []): void
    {
        $this->client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content, 'Ответ без тела.');

        $decoded = json_decode($content, true);
        self::assertIsArray($decoded, sprintf('Тело не JSON: %s', substr($content, 0, 200)));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
