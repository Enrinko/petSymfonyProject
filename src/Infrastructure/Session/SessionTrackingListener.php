<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Domain\Session\UserSession;
use App\Domain\Session\UserSessionRepositoryInterface;
use App\Domain\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Учёт активных сессий и их дистанционное завершение.
 *
 * - LoginSuccess: регистрирует сессию (ua, ip);
 * - каждый запрос: продлевает lastSeenAt (троттлинг в entity) и сверяет
 *   сессию со списком — если запись удалили («завершить сессию»),
 *   текущий запрос разлогинивается: kill-list вместо прямого удаления
 *   Redis-ключа (в БД хранится только хэш id, ключ по нему не найти).
 */
final readonly class SessionTrackingListener implements EventSubscriberInterface
{
    /**
     * Флаг «сессия на учёте» в самой сессии: kill-list применяется только
     * к сессиям, прошедшим штатный вход (LoginSuccess). Сессии без флага
     * (созданные до деплоя фичи, тестовый loginUser, будущие impersonation)
     * не считаются завершёнными — их просто нет в реестре.
     */
    private const string TRACKED_FLAG = '_session_tracked';

    public function __construct(
        private UserSessionRepositoryInterface $sessions,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            // После firewall (8): токен уже восстановлен из сессии
            KernelEvents::REQUEST => ['onRequest', 4],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        if (!$user instanceof User || $user->getId() === null || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        // Сессия в этот момент ещё ленивая: токен запишет в неё ContextListener
        // на kernel.response, и только тогда родился бы настоящий id. Стартуем
        // явно, чтобы зафиксировать тот id, под которым сессия и сохранится.
        if (!$session->isStarted()) {
            $session->start();
        }

        $hash = UserSession::hashOf($session->getId());
        $session->set(self::TRACKED_FLAG, true);

        // Повторный вход в той же сессии (re-login после REMEMBERED) — не дубль
        if ($this->sessions->findByHash($hash) !== null) {
            return;
        }

        $this->sessions->save(UserSession::open(
            $hash,
            $user->getId(),
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        ));
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasSession() || !$request->getSession()->isStarted()) {
            return;
        }

        if (!$this->security->getUser() instanceof User) {
            return;
        }

        $session = $request->getSession();

        if ($session->get(self::TRACKED_FLAG) !== true) {
            return;
        }

        $record = $this->sessions->findByHash(UserSession::hashOf($session->getId()));

        if ($record === null) {
            // Сессию завершили с другого устройства — гасим её и здесь.
            // (Свежий вход не теряется: LoginSuccess уже создал запись.)
            $this->security->logout(false);

            $event->setResponse(
                str_starts_with($request->getPathInfo(), '/api')
                    ? new JsonResponse(
                        ['message' => 'Сессия завершена на другом устройстве.', 'errors' => null],
                        401,
                    )
                    : new RedirectResponse($this->urlGenerator->generate('app_login', ['expired' => 1])),
            );

            return;
        }

        if ($record->touch(new \DateTimeImmutable())) {
            $this->sessions->save($record);
        }
    }
}
