<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Применяет локаль из LocaleResolver к запросу.
 *
 * Priority 6 — после файрвола (8), чтобы видеть аутентифицированного
 * пользователя. LocaleSwitcher — чтобы переводчик и прочие LocaleAware-сервисы
 * получили локаль независимо от штатного LocaleAwareListener (он срабатывает
 * раньше по приоритету и нашу локаль не увидит).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class LocaleRequestListener
{
    public function __construct(
        private LocaleResolver $localeResolver,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $locale = $this->localeResolver->resolve($event->getRequest());

        $event->getRequest()->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);
    }
}
