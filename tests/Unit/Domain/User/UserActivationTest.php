<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\User\Role;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class UserActivationTest extends TestCase
{
    public function testFreshUserIsActive(): void
    {
        $user = User::register('teacher@example.test', 'hash');

        self::assertTrue($user->isActive());
        self::assertNull($user->getDeactivatedAt());
    }

    public function testDeactivateMarksTimestampAndFlag(): void
    {
        $user = User::register('teacher@example.test', 'hash');

        $user->deactivate();

        self::assertFalse($user->isActive());
        self::assertNotNull($user->getDeactivatedAt());
    }

    public function testActivateClearsDeactivation(): void
    {
        $user = User::register('teacher@example.test', 'hash');
        $user->deactivate();

        $user->activate();

        self::assertTrue($user->isActive());
        self::assertNull($user->getDeactivatedAt());
    }

    public function testIsAdmin(): void
    {
        $user = User::register('teacher@example.test', 'hash');
        self::assertFalse($user->isAdmin());

        $user->changeRoles([Role::User->value, Role::Admin->value]);
        self::assertTrue($user->isAdmin());
    }
}
