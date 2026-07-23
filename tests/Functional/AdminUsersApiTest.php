<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\Role;
use App\Domain\User\User;

/**
 * Админ-API управления ролями: пагинация, поиск, смена ролей,
 * запрет самопонижения.
 */
final class AdminUsersApiTest extends FunctionalTestCase
{
    public function testListWithSearch(): void
    {
        $needle = uniqid();
        $this->createUser(sprintf('findme-%s@example.test', $needle));
        $this->createUser();

        $this->client->loginUser($this->admin());

        $this->jsonRequest('GET', '/api/admin/users?search=findme-'.$needle);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $body = $this->json();
        self::assertSame(1, $body['total']);
        self::assertIsArray($body['users']);
        self::assertCount(1, $body['users']);
    }

    public function testAdminCanPromoteUserToModerator(): void
    {
        $target = $this->createUser();
        $this->client->loginUser($this->admin());

        $this->jsonRequest(
            'PATCH',
            sprintf('/api/admin/users/%d/roles', $target->getId()),
            ['roles' => [Role::User->value, Role::Moderator->value]],
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertIsArray($body['roles'] ?? null);
        self::assertContains(Role::Moderator->value, $body['roles']);
    }

    public function testAdminCannotDemoteThemself(): void
    {
        $admin = $this->admin();
        $this->client->loginUser($admin);

        $this->jsonRequest(
            'PATCH',
            sprintf('/api/admin/users/%d/roles', $admin->getId()),
            ['roles' => [Role::User->value]],
        );

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertArrayHasKey('message', $this->json());
    }

    public function testUnknownRoleRejected(): void
    {
        $target = $this->createUser();
        $this->client->loginUser($this->admin());

        $this->jsonRequest(
            'PATCH',
            sprintf('/api/admin/users/%d/roles', $target->getId()),
            ['roles' => ['ROLE_SUPERHERO']],
        );

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    public function testModeratorCannotManageRoles(): void
    {
        $target = $this->createUser();
        $this->client->loginUser($this->createUser(roles: [Role::Moderator->value]));

        $this->jsonRequest(
            'PATCH',
            sprintf('/api/admin/users/%d/roles', $target->getId()),
            ['roles' => [Role::User->value, Role::Moderator->value]],
        );

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    private function admin(): User
    {
        return $this->createUser(roles: [Role::Admin->value]);
    }
}
