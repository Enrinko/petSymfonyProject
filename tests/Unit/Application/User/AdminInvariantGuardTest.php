<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\AdminInvariantGuard;
use App\Domain\User\Exception\LastActiveAdminException;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class AdminInvariantGuardTest extends TestCase
{
    private InMemoryUserRepository $users;
    private AdminInvariantGuard $guard;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->guard = new AdminInvariantGuard($this->users);
    }

    private function admin(int $id, bool $active = true): User
    {
        $user = User::register(sprintf('admin%d@example.test', $id), 'hash');
        $user->changeRoles([Role::User->value, Role::Admin->value]);

        if (!$active) {
            $user->deactivate();
        }

        $this->setId($user, $id);
        $this->users->withUser($id, $user);

        return $user;
    }

    private function setId(User $user, int $id): void
    {
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
    }

    public function testCannotStripAdminRoleFromLastActiveAdmin(): void
    {
        $last = $this->admin(1);

        $this->expectException(LastActiveAdminException::class);

        $this->guard->assertCanChangeRoles($last, [Role::User->value]);
    }

    public function testCanStripAdminRoleWhenAnotherActiveAdminExists(): void
    {
        $target = $this->admin(1);
        $this->admin(2);

        $this->expectNotToPerformAssertions();

        $this->guard->assertCanChangeRoles($target, [Role::User->value]);
    }

    public function testInactiveAdminDoesNotCountAsBackup(): void
    {
        $target = $this->admin(1);
        $this->admin(2, active: false);

        $this->expectException(LastActiveAdminException::class);

        $this->guard->assertCanChangeRoles($target, [Role::User->value]);
    }

    public function testKeepingAdminRoleIsAlwaysAllowed(): void
    {
        $last = $this->admin(1);

        $this->expectNotToPerformAssertions();

        $this->guard->assertCanChangeRoles($last, [Role::User->value, Role::Admin->value]);
    }

    public function testChangingRolesOfNonAdminIsAllowed(): void
    {
        $user = User::register('plain@example.test', 'hash');
        $this->setId($user, 5);
        $this->users->withUser(5, $user);
        $this->admin(1);

        $this->expectNotToPerformAssertions();

        $this->guard->assertCanChangeRoles($user, [Role::User->value, Role::Moderator->value]);
    }

    public function testCannotDeactivateLastActiveAdmin(): void
    {
        $last = $this->admin(1);

        $this->expectException(LastActiveAdminException::class);

        $this->guard->assertCanDeactivate($last);
    }

    public function testCanDeactivateAdminWhenAnotherActiveAdminExists(): void
    {
        $target = $this->admin(1);
        $this->admin(2);

        $this->expectNotToPerformAssertions();

        $this->guard->assertCanDeactivate($target);
    }

    public function testCanDeactivatePlainUserEvenWithSingleAdmin(): void
    {
        $this->admin(1);
        $user = User::register('plain@example.test', 'hash');
        $this->setId($user, 5);
        $this->users->withUser(5, $user);

        $this->expectNotToPerformAssertions();

        $this->guard->assertCanDeactivate($user);
    }
}
