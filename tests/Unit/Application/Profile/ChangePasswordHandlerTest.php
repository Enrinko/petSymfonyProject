<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Profile;

use App\Application\Profile\ChangePasswordCommand;
use App\Application\Profile\ChangePasswordHandler;
use App\Domain\Audit\AuditAction;
use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\User;
use App\Tests\Fake\FakePasswordHasherFactory;
use App\Tests\Fake\InMemoryPasswordResetTokenRepository;
use App\Tests\Fake\InMemoryUserRepository;
use App\Tests\Fake\SpyAuditLogger;
use PHPUnit\Framework\TestCase;

final class ChangePasswordHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private ChangePasswordHandler $handler;
    private SpyAuditLogger $audit;
    private InMemoryPasswordResetTokenRepository $resetTokens;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->resetTokens = new InMemoryPasswordResetTokenRepository();
        $this->handler = new ChangePasswordHandler(
            $this->users,
            new FakePasswordHasherFactory(),
            $this->audit = new SpyAuditLogger(),
            $this->resetTokens,
        );
    }

    public function testChangesPasswordWhenCurrentMatches(): void
    {
        $user = User::register('teacher@example.test', 'hashed:OldPassword#1');
        $this->users->save($user);

        ($this->handler)($user, new ChangePasswordCommand(
            currentPassword: 'OldPassword#1',
            newPassword: 'NewSecure#Password2',
            newPasswordConfirm: 'NewSecure#Password2',
        ));

        self::assertSame('hashed:NewSecure#Password2', $user->getPassword());
        self::assertSame(AuditAction::PasswordChanged, $this->audit->lastAction());
        self::assertSame(1, $this->resetTokens->deleteForUserCalls, 'Смена пароля гасит выданные токены сброса');
    }

    public function testWrongCurrentPasswordDoesNotDropResetTokens(): void
    {
        $user = User::register('teacher@example.test', 'hashed:OldPassword#1');
        $this->users->save($user);

        try {
            ($this->handler)($user, new ChangePasswordCommand('nope', 'NewSecure#Password2', 'NewSecure#Password2'));
        } catch (InvalidCurrentPasswordException) {
            // ожидаемо
        }

        self::assertSame(0, $this->resetTokens->deleteForUserCalls);
    }

    public function testRejectsWrongCurrentPassword(): void
    {
        $user = User::register('teacher@example.test', 'hashed:OldPassword#1');
        $this->users->save($user);

        $this->expectException(InvalidCurrentPasswordException::class);

        ($this->handler)($user, new ChangePasswordCommand(
            currentPassword: 'guess-wrong',
            newPassword: 'NewSecure#Password2',
            newPasswordConfirm: 'NewSecure#Password2',
        ));
    }

    public function testWrongCurrentPasswordLeavesHashUntouched(): void
    {
        $user = User::register('teacher@example.test', 'hashed:OldPassword#1');
        $this->users->save($user);

        try {
            ($this->handler)($user, new ChangePasswordCommand('nope', 'NewSecure#Password2', 'NewSecure#Password2'));
        } catch (InvalidCurrentPasswordException) {
            // ожидаемо
        }

        self::assertSame('hashed:OldPassword#1', $user->getPassword());
    }
}
