<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventRepositoryInterface;
use App\Domain\Metrics\MetricsInterface;
use App\Domain\User\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Входы/выходы пишутся из нативных security-событий: сюда попадает
 * и json_login, и remember-me. Для неудачного входа актора нет —
 * фиксируются причина и IP (email не пишем: это может быть перебор чужих).
 *
 * Пишет напрямую в AuditEventRepositoryInterface, минуя декорированный
 * MetricsAuditLogger (см. src/Infrastructure/Metrics/MetricsAuditLogger.php),
 * поэтому счётчик audit_events_total ведёт сам — тем же именем и лейблом.
 */
final readonly class SecurityEventsAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditEventRepositoryInterface $events,
        private MetricsInterface $metrics,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // ContextListener (восстановление сессии) сюда не попадает — только
        // реальные входы: json_login и remember-me
        $this->persist(AuditEvent::record(
            AuditAction::LoginSucceeded,
            $user->getId(),
            $user->getEmail(),
            'user',
            (string) $user->getId(),
            $event->getRequest()->getClientIp(),
            $event->getRequest()->headers->get('User-Agent'),
            ['authenticator' => (new \ReflectionClass($event->getAuthenticator()))->getShortName()],
        ));
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $this->persist(AuditEvent::record(
            AuditAction::LoginFailed,
            null,
            null,
            null,
            null,
            $event->getRequest()->getClientIp(),
            $event->getRequest()->headers->get('User-Agent'),
            ['reason' => (new \ReflectionClass($event->getException()))->getShortName()],
        ));
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->persist(AuditEvent::record(
            AuditAction::LoggedOut,
            $user->getId(),
            $user->getEmail(),
            'user',
            (string) $user->getId(),
            $event->getRequest()->getClientIp(),
        ));
    }

    private function persist(AuditEvent $event): void
    {
        try {
            $this->events->save($event);
        } catch (\Throwable) {
            // Аудит не должен ломать вход/выход; сбой заметен по пустому журналу
        }

        // Тот же счётчик и лейбл, что у MetricsAuditLogger — считаем даже
        // если запись в журнал выше не удалась, симметрично декоратору.
        $this->metrics->increment('audit_events_total', ['action' => $event->getAction()]);
    }
}
