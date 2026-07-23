<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class UserProfileTest extends TestCase
{
    public function testRenameTrimsAndStoresName(): void
    {
        $user = User::register('teacher@example.test', 'hash');

        $user->rename('  Анна Скрипичная  ');

        self::assertSame('Анна Скрипичная', $user->getDisplayName());
        self::assertSame('Анна Скрипичная', $user->getDisplayLabel());
    }

    public function testRenameWithEmptyStringClearsName(): void
    {
        $user = User::register('teacher@example.test', 'hash');
        $user->rename('Анна');

        $user->rename('   ');

        self::assertNull($user->getDisplayName());
        self::assertSame('teacher@example.test', $user->getDisplayLabel());
    }

    public function testInitialsFromDisplayName(): void
    {
        $user = User::register('teacher@example.test', 'hash');
        $user->rename('Анна Скрипичная');

        self::assertSame('АС', $user->getInitials());
    }

    public function testInitialsFallBackToEmail(): void
    {
        $user = User::register('anna.violin@example.test', 'hash');

        self::assertSame('AV', $user->getInitials());
    }

    public function testChangeAvatarStoresAndClearsPath(): void
    {
        $user = User::register('teacher@example.test', 'hash');

        $user->changeAvatar('/uploads/avatars/1.webp');
        self::assertSame('/uploads/avatars/1.webp', $user->getAvatarPath());

        $user->changeAvatar(null);
        self::assertNull($user->getAvatarPath());
    }
}
