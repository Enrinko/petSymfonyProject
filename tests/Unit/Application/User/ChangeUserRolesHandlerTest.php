<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\AdminInvariantGuard;
use App\Application\User\ChangeUserRolesCommand;
use App\Application\User\ChangeUserRolesHandler;
use App\Domain\Audit\AuditAction;
use App\Domain\User\Exception\CannotRemoveOwnAdminRoleException;
use App\Domain\User\Exception\LastActiveAdminException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryUserRepository;
use App\Tests\Fake\SpyAuditLogger;
use PHPUnit\Framework\TestCase;

final class ChangeUserRolesHandlerTest extends TestCase
{
    private SpyAuditLogger $audit;

    protected function setUp(): void
    {
        $this->audit = new SpyAuditLogger();
    }

    private function handler(InMemoryUserRepository $users): ChangeUserRolesHandler
    {
        return new ChangeUserRolesHandler($users, new AdminInvariantGuard($users), $this->audit);
    }

    private function withId(User $user, int $id): User
    {
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }

    public function testGrantsModeratorRole(): void
    {
        $target = User::register('user@example.com', 'hash');
        $users = (new InMemoryUserRepository())->withUser(2, $target);

        $updated = $this->handler($users)(
            new ChangeUserRolesCommand(userId: 2, roles: ['ROLE_USER', 'ROLE_MODERATOR'], actorId: 1),
        );

        self::assertContains('ROLE_MODERATOR', $updated->getRoles());
        self::assertCount(1, $users->saved);
    }

    public function testAdminCanRevokeAdminRoleOfAnotherAdminWhenBackupExists(): void
    {
        $target = $this->withId(User::register('other-admin@example.com', 'hash'), 2);
        $target->changeRoles(['ROLE_ADMIN']);
        $actor = $this->withId(User::register('actor-admin@example.com', 'hash'), 1);
        $actor->changeRoles(['ROLE_ADMIN']);
        $users = (new InMemoryUserRepository())->withUser(2, $target)->withUser(1, $actor);

        $updated = $this->handler($users)(new ChangeUserRolesCommand(userId: 2, roles: ['ROLE_USER'], actorId: 1));

        self::assertNotContains('ROLE_ADMIN', $updated->getRoles());

        // Журнал: старые и новые роли зафиксированы
        self::assertSame(AuditAction::RolesChanged, $this->audit->lastAction());
        $entry = $this->audit->entries[0];
        self::assertSame('2', $entry['subjectId']);
        self::assertContains('ROLE_ADMIN', $entry['payload']['old']);
        self::assertNotContains('ROLE_ADMIN', $entry['payload']['new']);
    }

    public function testCannotRevokeAdminRoleOfLastActiveAdmin(): void
    {
        // Актор — модератор без админки: единственный активный админ — target
        $target = $this->withId(User::register('last-admin@example.com', 'hash'), 2);
        $target->changeRoles(['ROLE_ADMIN']);
        $users = (new InMemoryUserRepository())->withUser(2, $target);

        $this->expectException(LastActiveAdminException::class);

        $this->handler($users)(new ChangeUserRolesCommand(userId: 2, roles: ['ROLE_USER'], actorId: 1));
    }

    public function testAdminCannotRevokeOwnAdminRole(): void
    {
        $self = $this->withId(User::register('admin@example.com', 'hash'), 1);
        $self->changeRoles(['ROLE_ADMIN']);
        $users = (new InMemoryUserRepository())->withUser(1, $self);

        $this->expectException(CannotRemoveOwnAdminRoleException::class);

        $this->handler($users)(new ChangeUserRolesCommand(userId: 1, roles: ['ROLE_USER'], actorId: 1));
    }

    public function testAdminCanChangeOwnRolesWhileKeepingAdmin(): void
    {
        $self = $this->withId(User::register('admin@example.com', 'hash'), 1);
        $self->changeRoles(['ROLE_ADMIN']);
        $users = (new InMemoryUserRepository())->withUser(1, $self);

        $updated = $this->handler($users)(new ChangeUserRolesCommand(
            userId: 1,
            roles: ['ROLE_USER', 'ROLE_MODERATOR', 'ROLE_ADMIN'],
            actorId: 1,
        ));

        self::assertContains('ROLE_MODERATOR', $updated->getRoles());
        self::assertContains('ROLE_ADMIN', $updated->getRoles());
    }

    public function testFailsForUnknownUser(): void
    {
        $users = new InMemoryUserRepository();

        $this->expectException(UserNotFoundException::class);

        $this->handler($users)(new ChangeUserRolesCommand(userId: 42, roles: ['ROLE_USER'], actorId: 1));
    }
}
