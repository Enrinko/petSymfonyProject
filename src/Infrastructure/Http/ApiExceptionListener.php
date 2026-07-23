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

/**
 * Единый конверт ошибок API: {"message": string, "errors": object|null}.
 *
 * Перехватывает только пути ^/api. Сообщения берутся из карты по статусу,
 * а не из исключения — внутренние тексты исключений не утекают наружу.
 * Приоритет ниже security-слушателя: 401/redirect от entry point не перебиваем.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -8)]
final readonly class ApiExceptionListener
{
    private const array MESSAGES = [
        400 => 'Некорректный запрос.',
        401 => 'Требуется аутентификация.',
        403 => 'Доступ запрещён.',
        404 => 'Не найдено.',
        405 => 'Метод не поддерживается.',
        409 => 'Конфликт данных.',
        422 => 'Данные не прошли валидацию.',
        429 => 'Слишком много запросов. Попробуйте позже.',
    ];

    public function __construct(
        #[Autowire('%kernel.debug%')]
        private bool $debug,
        private RequestIdProvider $requestId,
        private LoggerInterface $logger,
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

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $response = new JsonResponse(
                ['message' => self::MESSAGES[$status] ?? 'Ошибка запроса.', 'errors' => null],
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
                'message' => sprintf('Внутренняя ошибка сервера. Код обращения: %s', $this->requestId->get()),
                'errors' => null,
            ],
            500,
        ));
    }
}
