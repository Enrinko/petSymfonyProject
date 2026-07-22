<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\ChangeUserRolesCommand;
use App\Application\User\ChangeUserRolesHandler;
use App\Domain\User\Exception\CannotRemoveOwnAdminRoleException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class ChangeUserRolesHandlerTest extends TestCase
{
    public function testGrantsModeratorRole(): void
    {
        $target = User::register('user@example.com', 'hash');
        $users = (new InMemoryUserRepository())->withUser(2, $target);

        $handler = new ChangeUserRolesHandler($users);
        $updated = $handler(new ChangeUserRolesCommand(userId: 2, roles: ['ROLE_USER', 'ROLE_MODERATOR'], actorId: 1));

        self::assertContains('ROLE_MODERATOR', $updated->getRoles());
        self::assertCount(1, $users->saved);
    }

    public function testAdminCanRevokeAdminRoleOfAnotherAdmin(): void
    {
        $target = User::register('other-admin@example.com', 'hash');
        $target->changeRoles(['ROLE_ADMIN']);
        $users = (new InMemoryUserRepository())->withUser(2, $target);

        $handler = new ChangeUserRolesHandler($users);
        $updated = $handler(new ChangeUserRolesCommand(userId: 2, roles: ['ROLE_USER'], actorId: 1));

        self::assertNotContains('ROLE_ADMIN', $updated->getRoles());
    }

    public function testAdminCannotRevokeOwnAdminRole(): void
    {
        $self = User::register('admin@example.com', 'hash');
        $self->changeRoles(['ROLE_ADMIN']);
        $users = (new InMemoryUserRepository())->withUser(1, $self);

        $handler = new ChangeUserRolesHandler($users);

        $this->expectException(CannotRemoveOwnAdminRoleException::class);

        $handler(new ChangeUserRolesCommand(userId: 1, roles: ['ROLE_USER'], actorId: 1));
    }

    public function testAdminCanChangeOwnRolesWhileKeepingAdmin(): void
    {
        $self = User::register('admin@example.com', 'hash');
        $self->changeRoles(['ROLE_ADMIN']);
        $users = (new InMemoryUserRepository())->withUser(1, $self);

        $handler = new ChangeUserRolesHandler($users);
        $updated = $handler(new ChangeUserRolesCommand(
            userId: 1,
            roles: ['ROLE_USER', 'ROLE_MODERATOR', 'ROLE_ADMIN'],
            actorId: 1,
        ));

        self::assertContains('ROLE_MODERATOR', $updated->getRoles());
        self::assertContains('ROLE_ADMIN', $updated->getRoles());
    }

    public function testFailsForUnknownUser(): void
    {
        $handler = new ChangeUserRolesHandler(new InMemoryUserRepository());

        $this->expectException(UserNotFoundException::class);

        $handler(new ChangeUserRolesCommand(userId: 42, roles: ['ROLE_USER'], actorId: 1));
    }
}
