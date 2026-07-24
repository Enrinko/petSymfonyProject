<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\RegisterUserCommand;
use App\Application\User\RegisterUserHandler;
use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\User;
use App\Tests\Fake\FakeMetrics;
use App\Tests\Fake\FakePasswordHasherFactory;
use App\Tests\Fake\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class RegisterUserHandlerTest extends TestCase
{
    public function testRegistersUserWithHashedPasswordAndNormalizedEmail(): void
    {
        $users = new InMemoryUserRepository();
        $metrics = new FakeMetrics();
        $handler = new RegisterUserHandler($users, new FakePasswordHasherFactory(), $metrics);

        $user = $handler(new RegisterUserCommand('  NEW@Example.COM ', 'password123', 'password123'));

        self::assertSame('new@example.com', $user->getEmail());
        self::assertSame('hashed:password123', $user->getPassword());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertCount(1, $users->saved);
        self::assertSame([['name' => 'users_registered_total', 'labels' => []]], $metrics->increments);
    }

    public function testRejectsDuplicateEmail(): void
    {
        $users = new InMemoryUserRepository();
        $users->withUser(1, User::register('taken@example.com', 'hash'));

        $metrics = new FakeMetrics();
        $handler = new RegisterUserHandler($users, new FakePasswordHasherFactory(), $metrics);

        $this->expectException(EmailAlreadyInUseException::class);

        try {
            $handler(new RegisterUserCommand('taken@example.com', 'password123', 'password123'));
        } finally {
            self::assertSame([], $metrics->increments);
        }
    }
}
