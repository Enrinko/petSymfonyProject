<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Http\LocaleResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ошибки json_login в едином конверте API:
 * неверные данные — 401, сработавший login_throttling — 429,
 * статусные отказы (деактивация) — 401 со своим сообщением.
 *
 * Локаль резолвим явно: обработчик срабатывает внутри файрвола,
 * ДО LocaleRequestListener (priority 6 < 8).
 */
final readonly class AppAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        // Канал security: monolog-бандл автовайрит по имени аргумента
        private LoggerInterface $securityLogger,
        private TranslatorInterface $translator,
        private LocaleResolver $localeResolver,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Email из тела не логируем целиком — журналу достаточно факта и IP
        $this->securityLogger->info('Login failed.', [
            'reason' => (new \ReflectionClass($exception))->getShortName(),
            'ip' => $request->getClientIp(),
        ]);

        $locale = $this->localeResolver->resolve($request);

        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['message' => $this->translator->trans('auth.login.throttled', [], null, $locale), 'errors' => null],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        // Статусный отказ приходит из ActiveUserChecker::checkPostAuth — то есть
        // ТОЛЬКО после успешной сверки пароля. Владелец аккаунта с верным паролем
        // видит внятную причину; для чужого email пароль не сойдётся и сюда не дойдёт
        // (никакого enumeration). messageKey — ключ каталога; не найдётся — вернётся как есть
        if ($exception instanceof AccountStatusException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->trans(
                        $exception->getMessageKey(),
                        $exception->getMessageData(),
                        null,
                        $locale,
                    ),
                    'errors' => null,
                ],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new JsonResponse(
            ['message' => $this->translator->trans('auth.login.failed', [], null, $locale), 'errors' => null],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
