<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\Role;
use App\Domain\User\User;

/**
 * Деактивация аккаунтов. Инвариант «последний активный админ» по HTTP
 * недостижим для чужих целей (актор — сам активный админ и остаётся в строю),
 * а self-случаи запрещены отдельными правилами — глубинные ветки guard'а
 * покрыты юнитами AdminInvariantGuardTest.
 */
final class AdminUserStatusTest extends FunctionalTestCase
{
    public function testAdminDeactivatesAndReactivatesUser(): void
    {
        $target = $this->createUser();
        $this->client->loginUser($this->admin());

        $this->jsonRequest('PATCH', $this->statusUrl($target), ['active' => false]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertFalse($body['isActive']);
        self::assertNotNull($body['deactivatedAt']);

        $this->jsonRequest('PATCH', $this->statusUrl($target), ['active' => true]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertTrue($this->json()['isActive']);
    }

    public function testDeactivatedUserCannotLogIn(): void
    {
        $target = $this->createUser();
        $this->client->loginUser($this->admin());
        $this->jsonRequest('PATCH', $this->statusUrl($target), ['active' => false]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->client->restart();
        $this->jsonRequest('POST', '/api/login', [
            'email' => $target->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('деактивирован', (string) $this->json()['message']);
    }

    public function testDeactivationKillsLiveSession(): void
    {
        $target = $this->createUser();

        // Жертва залогинена и работает
        $this->client->loginUser($target);
        $this->client->request('GET', '/clients');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Администратор деактивирует её из другого места
        $target->deactivate();
        static::getContainer()->get('doctrine.orm.entity_manager')->flush();

        // Следующий запрос жертвы: user checker гасит сессию
        $this->client->request('GET', '/clients');
        self::assertNotSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Деактивированный обязан потерять живую сессию.',
        );
    }

    public function testSelfDeactivationRejected(): void
    {
        $admin = $this->admin();
        $this->admin(); // запасной активный админ: срабатывает именно self-запрет
        $this->client->loginUser($admin);

        $this->jsonRequest('PATCH', $this->statusUrl($admin), ['active' => false]);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertIsArray($body['errors'] ?? null);
        self::assertArrayHasKey('active', $body['errors']);
    }

    public function testDeactivatingAnotherAdminKeepsActorInCharge(): void
    {
        $victim = $this->admin();
        $this->client->loginUser($this->admin());

        // Актор остаётся активным админом — инвариант цел, операция разрешена
        $this->jsonRequest('PATCH', $this->statusUrl($victim), ['active' => false]);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertFalse($this->json()['isActive']);
    }

    public function testRolesOfInactiveUserAreLocked(): void
    {
        $target = $this->createUser(roles: [Role::Moderator->value]);
        $this->client->loginUser($this->admin());

        $this->jsonRequest('PATCH', $this->statusUrl($target), ['active' => false]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Voter: роли неактивного не редактируются — сначала активация
        $this->jsonRequest('PATCH', sprintf('/api/admin/users/%d/roles', $target->getId()), [
            'roles' => [Role::User->value],
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testModeratorCannotChangeStatus(): void
    {
        $target = $this->createUser();
        $this->client->loginUser($this->createUser(roles: [Role::Moderator->value]));

        $this->jsonRequest('PATCH', $this->statusUrl($target), ['active' => false]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testListExposesActivityFlag(): void
    {
        $target = $this->createUser();
        $this->client->loginUser($this->admin());
        $this->jsonRequest('PATCH', $this->statusUrl($target), ['active' => false]);

        $this->jsonRequest('GET', '/api/admin/users?search=' . urlencode($target->getEmail()));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertIsArray($body['users']);
        self::assertCount(1, $body['users']);
        self::assertIsArray($body['users'][0]);
        self::assertFalse($body['users'][0]['isActive']);
    }

    private function statusUrl(User $user): string
    {
        return sprintf('/api/admin/users/%d/status', $user->getId());
    }

    private function admin(): User
    {
        return $this->createUser(roles: [Role::Admin->value]);
    }
}
