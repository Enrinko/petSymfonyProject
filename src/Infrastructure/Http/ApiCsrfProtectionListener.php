<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CSRF-защита изменяющих /api-запросов — защита в глубину поверх SameSite-куки.
 *
 * Схема stateless (без токен-состояния на запрос):
 *  1. обязательный маркер `X-Requested-With: XMLHttpRequest` — его ставит только
 *     наш httpClient; HTML-форма (включая трюк с enctype="text/plain") добавить
 *     произвольный заголовок не может без CORS-preflight;
 *  2. `Sec-Fetch-Site`, если браузер его прислал, обязан быть same-origin
 *     (или `none` — прямой переход, не относящийся к fetch);
 *  3. `Origin`, если прислан, обязан совпадать со схемой и хостом запроса.
 *
 * Приоритет выше firewall'а (8): json_login не должен успеть обработать
 * подделанный POST /api/login (login CSRF), а CSRF-мусор отсекается до
 * аутентификации и не тратит счётчик login_throttling. Карта доступа для
 * анонимных GET не меняется — безопасные методы пропускаются.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final class ApiCsrfProtectionListener
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (\in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        if (!$this->looksLikeOwnClient($request)) {
            throw new AccessDeniedHttpException('Запрос отклонён CSRF-защитой.');
        }
    }

    private function looksLikeOwnClient(Request $request): bool
    {
        if ($request->headers->get('X-Requested-With') !== 'XMLHttpRequest') {
            return false;
        }

        $secFetchSite = $request->headers->get('Sec-Fetch-Site');

        if ($secFetchSite !== null && !\in_array($secFetchSite, ['same-origin', 'none'], true)) {
            return false;
        }

        $origin = $request->headers->get('Origin');

        // "null" шлют sandbox-iframe и цепочки редиректов — не доверяем
        if ($origin !== null && $origin !== $request->getSchemeAndHttpHost()) {
            return false;
        }

        return true;
    }
}
