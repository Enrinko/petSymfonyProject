<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Централизованный перевод сообщений об ошибках API.
 *
 * Контроллеры отдают ApiJson::error('api.<ключ>', ...) — ключ каталога вместо
 * готового текста. Здесь, на kernel.response, ключ переводится по локали запроса.
 * Так контроллеры не тянут переводчик, а EN-пользователь видит ошибки на своём языке.
 *
 * Трогаем любой JSON-ответ под ^/api с message-ключом вида «api.» (ошибки И
 * success-сообщения вроде «пароль изменён»); готовый текст без префикса «api.»
 * (напр. из ApiExceptionListener) пропускается.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: 0)]
final readonly class ApiErrorLocaleListener
{
    public function __construct(
        private TranslatorInterface $translator,
        private LocaleResolver $localeResolver,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $response = $event->getResponse();

        if (!$response instanceof JsonResponse) {
            return;
        }

        /** @var mixed $data */
        $data = json_decode((string) $response->getContent(), true);

        if (!\is_array($data) || !isset($data['message']) || !\is_string($data['message'])) {
            return;
        }

        if (!str_starts_with($data['message'], 'api.')) {
            return;
        }

        // resolve() на kernel.response: lazy-firewall уже разрешил токен, поэтому
        // локаль залогиненного пользователя (профиль) видна — в отличие от
        // request->getLocale(), выставленной до контроллера на priority 6
        $locale = $this->localeResolver->resolve($request);

        $data['message'] = $this->translator->trans($data['message'], [], null, $locale);
        $response->setData($data);
    }
}
