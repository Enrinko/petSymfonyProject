<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Logging\RequestIdProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Единый конверт ошибок API: {"message": string, "errors": object|null}.
 *
 * Перехватывает только пути ^/api. Сообщения берутся из карты по статусу
 * (ключи каталога → перевод по локали запроса), а не из исключения —
 * внутренние тексты исключений не утекают наружу.
 * Приоритет ниже security-слушателя: 401/redirect от entry point не перебиваем.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -8)]
final readonly class ApiExceptionListener
{
    private const array MESSAGE_KEYS = [
        400 => 'api.http.bad_request',
        401 => 'api.http.unauthorized',
        403 => 'api.http.forbidden',
        404 => 'api.http.not_found',
        405 => 'api.http.method_not_allowed',
        409 => 'api.http.conflict',
        422 => 'api.validation_failed',
        429 => 'api.http.too_many_requests',
    ];

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug,
        private RequestIdProvider $requestId,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private LocaleResolver $localeResolver,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        if ($event->getResponse() !== null) {
            return;
        }

        $exception = $event->getThrowable();

        $locale = $this->localeResolver->resolve($request);

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $message = $this->translator->trans(self::MESSAGE_KEYS[$status] ?? 'api.http.error', [], null, $locale);
            $response = new JsonResponse(
                ['message' => $message, 'errors' => null],
                $status,
                $exception->getHeaders(),
            );
            $event->setResponse($response);

            return;
        }

        // 5xx фиксируется с контекстом маршрута; request_id доклеит processor
        $this->logger->error('Unhandled API exception.', [
            'exception' => $exception,
            'route' => $request->attributes->get('_route'),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ]);

        // Неожиданные исключения: в debug оставляем штатную страницу с трейсом.
        if ($this->debug) {
            return;
        }

        // «Сообщите этот код поддержке» — по нему инцидент находится в логах
        $event->setResponse(new JsonResponse(
            [
                'message' => $this->translator->trans(
                    'api.http.server_error',
                    ['%code%' => $this->requestId->get()],
                    null,
                    $locale,
                ),
                'errors' => null,
            ],
            500,
        ));
    }
}
