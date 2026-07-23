<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Вход: X-Request-Id от прокси (или лениво генерируется свой).
 * Выход: тот же id в заголовке ответа — «сообщите код поддержке».
 */
final readonly class RequestIdListener
{
    public function __construct(
        private RequestIdProvider $requestId,
    ) {
    }

    // Раньше всех прочих слушателей: id должен попасть даже в логи firewall'а
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 512)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $inbound = $event->getRequest()->headers->get('X-Request-Id');

        if ($inbound !== null) {
            $this->requestId->accept($inbound);
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set('X-Request-Id', $this->requestId->get());
    }
}
