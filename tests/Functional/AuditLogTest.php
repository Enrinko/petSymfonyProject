<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventRepositoryInterface;
use App\Domain\Audit\AuditFilter;
use App\Domain\User\Role;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Журнал безопасности: события пишутся из реальных точек
 * (логин, роли, статус), API отдаёт их админу с фильтрами.
 */
final class AuditLogTest extends FunctionalTestCase
{
    public function testSuccessfulLoginIsAudited(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $events = $this->eventsOf(AuditAction::LoginSucceeded);
        self::assertNotEmpty($events);
        $last = $events[0];
        self::assertSame($user->getEmail(), $last->getActorEmail());
        self::assertNotNull($last->getIp());
    }

    public function testFailedLoginIsAuditedWithoutActor(): void
    {
        $this->jsonRequest('POST', '/api/login', [
            'email' => 'ghost@example.test',
            'password' => 'wrong-password',
        ]);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        $events = $this->eventsOf(AuditAction::LoginFailed);
        self::assertNotEmpty($events);
        self::assertNull($events[0]->getActorId());
        self::assertArrayHasKey('reason', $events[0]->getPayload());
    }

    public function testRoleChangeIsAuditedWithOldAndNewRoles(): void
    {
        $target = $this->createUser();
        $admin = $this->createUser(roles: [Role::Admin->value]);
        $this->client->loginUser($admin);

        $this->jsonRequest('PATCH', sprintf('/api/admin/users/%d/roles', $target->getId()), [
            'roles' => [Role::User->value, Role::Moderator->value],
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $events = $this->eventsOf(AuditAction::RolesChanged);
        self::assertNotEmpty($events);
        $event = $events[0];
        self::assertSame($admin->getEmail(), $event->getActorEmail(), 'Актор — админ, а не цель.');
        self::assertSame((string) $target->getId(), $event->getSubjectId());
        self::assertContains(Role::Moderator->value, $event->getPayload()['new']);
    }

    public function testDeactivationIsAudited(): void
    {
        $target = $this->createUser();
        $this->client->loginUser($this->createUser(roles: [Role::Admin->value]));

        $this->jsonRequest('PATCH', sprintf('/api/admin/users/%d/status', $target->getId()), ['active' => false]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertNotEmpty($this->eventsOf(AuditAction::UserDeactivated));
    }

    public function testAuditApiRequiresAdmin(): void
    {
        $this->client->loginUser($this->createUser());

        $this->jsonRequest('GET', '/api/admin/audit');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAuditApiListsAndFilters(): void
    {
        $user = $this->createUser();

        // Генерируем два разных события: успешный вход и неудачный
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        $this->client->restart();
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => 'wrong-password',
        ]);

        $this->client->loginUser($this->createUser(roles: [Role::Admin->value]));

        // Без фильтра — есть и то и другое (плюс списки действий для UI)
        $this->jsonRequest('GET', '/api/admin/audit');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $body = $this->json();
        self::assertGreaterThanOrEqual(2, $body['total']);
        self::assertContains(AuditAction::LoginFailed->value, $body['actions']);

        // Фильтр по действию
        $this->jsonRequest('GET', '/api/admin/audit?action=' . AuditAction::LoginFailed->value);
        $body = $this->json();
        self::assertGreaterThanOrEqual(1, $body['total']);
        self::assertIsArray($body['events']);

        foreach ($body['events'] as $event) {
            self::assertIsArray($event);
            self::assertSame(AuditAction::LoginFailed->value, $event['action']);
        }

        // Фильтр по актору
        $this->jsonRequest('GET', '/api/admin/audit?actor=' . urlencode($user->getEmail()));
        $body = $this->json();
        self::assertGreaterThanOrEqual(1, $body['total']);

        // Фильтр по будущей дате — пусто
        $this->jsonRequest('GET', '/api/admin/audit?from=2099-01-01');
        self::assertSame(0, $this->json()['total']);
    }

    public function testPruneRemovesOnlyOldEvents(): void
    {
        $repository = $this->auditRepository();

        $old = AuditEvent::record(AuditAction::LoginFailed, ip: '10.0.0.1');
        $fresh = AuditEvent::record(AuditAction::LoginFailed, ip: '10.0.0.2');
        $repository->save($old);
        $repository->save($fresh);

        // Состариваем одну запись напрямую (occurredAt приватный и append-only)
        $ref = new \ReflectionProperty(AuditEvent::class, 'occurredAt');
        $ref->setValue($old, new \DateTimeImmutable('-400 days'));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $removed = $repository->pruneOlderThan(new \DateTimeImmutable('-365 days'));

        self::assertSame(1, $removed);
        self::assertNotNull($fresh->getId());
    }

    /**
     * @return list<AuditEvent>
     */
    private function eventsOf(AuditAction $action): array
    {
        return $this->auditRepository()->findPage(new AuditFilter(action: $action->value), 1, 10);
    }

    private function auditRepository(): AuditEventRepositoryInterface
    {
        return static::getContainer()->get(AuditEventRepositoryInterface::class);
    }
}
