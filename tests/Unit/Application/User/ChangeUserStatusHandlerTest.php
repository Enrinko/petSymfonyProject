<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\ChangeUserStatusCommand;
use App\Application\User\ChangeUserStatusHandler;
use App\Application\User\AdminInvariantGuard;
use App\Domain\Audit\AuditAction;
use App\Domain\User\Exception\CannotDeactivateSelfException;
use App\Domain\User\Exception\LastActiveAdminException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryUserRepository;
use App\Tests\Fake\SpyAuditLogger;
use PHPUnit\Framework\TestCase;

final class ChangeUserStatusHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private ChangeUserStatusHandler $handler;
    private SpyAuditLogger $audit;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->handler = new ChangeUserStatusHandler($this->users, new AdminInvariantGuard($this->users), $this->audit = new SpyAuditLogger());
    }

    private function user(int $id, bool $admin = false): User
    {
        $user = User::register(sprintf('user%d@example.test', $id), 'hash');

        if ($admin) {
            $user->changeRoles([Role::User->value, Role::Admin->value]);
        }

        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
        $this->users->withUser($id, $user);

        return $user;
    }

    public function testDeactivatesUser(): void
    {
        $target = $this->user(2);
        $this->user(1, admin: true);

        $result = ($this->handler)(new ChangeUserStatusCommand(userId: 2, actorId: 1, active: false));

        self::assertFalse($result->isActive());
        self::assertSame([$target], $this->users->saved);
        self::assertSame(AuditAction::UserDeactivated, $this->audit->lastAction());
    }

    public function testReactivatesUser(): void
    {
        $target = $this->user(2);
        $target->deactivate();
        $this->user(1, admin: true);

        $result = ($this->handler)(new ChangeUserStatusCommand(userId: 2, actorId: 1, active: true));

        self::assertTrue($result->isActive());
        self::assertSame(AuditAction::UserActivated, $this->audit->lastAction());
    }

    public function testSelfDeactivationRejected(): void
    {
        $this->user(1, admin: true);
        $this->user(2, admin: true); // второй админ: инвариант не мешает — ловим именно self

        $this->expectException(CannotDeactivateSelfException::class);

        ($this->handler)(new ChangeUserStatusCommand(userId: 1, actorId: 1, active: false));
    }

    public function testLastActiveAdminCannotBeDeactivated(): void
    {
        $this->user(1, admin: true);
        $this->user(2);

        $this->expectException(LastActiveAdminException::class);

        ($this->handler)(new ChangeUserStatusCommand(userId: 1, actorId: 2, active: false));
    }

    public function testUnknownUser(): void
    {
        $this->expectException(UserNotFoundException::class);

        ($this->handler)(new ChangeUserStatusCommand(userId: 99, actorId: 1, active: false));
    }
}
