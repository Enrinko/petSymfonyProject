<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\User\Exception\UnknownRoleException;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisterAssignsBaseRoleAndCreationDate(): void
    {
        $user = User::register('user@example.com', 'hash');

        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame('user@example.com', $user->getUserIdentifier());
        self::assertEqualsWithDelta(time(), $user->getCreatedAt()->getTimestamp(), 5);
    }

    public function testGetRolesAlwaysContainsBaseRole(): void
    {
        $user = User::register('user@example.com', 'hash');
        $user->changeRoles(['ROLE_ADMIN']);

        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testChangeRolesDeduplicates(): void
    {
        $user = User::register('user@example.com', 'hash');
        $user->changeRoles(['ROLE_MODERATOR', 'ROLE_MODERATOR', 'ROLE_USER']);

        self::assertSame(['ROLE_USER', 'ROLE_MODERATOR'], $user->getRoles());
    }

    public function testChangeRolesRejectsUnknownRole(): void
    {
        $user = User::register('user@example.com', 'hash');

        $this->expectException(UnknownRoleException::class);

        $user->changeRoles(['ROLE_HACKER']);
    }

    public function testChangePasswordReplacesHash(): void
    {
        $user = User::register('user@example.com', 'old-hash');
        $user->changePassword('new-hash');

        self::assertSame('new-hash', $user->getPassword());
    }
}
