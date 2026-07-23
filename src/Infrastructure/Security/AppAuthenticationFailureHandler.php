<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Ошибки json_login в едином конверте API:
 * неверные данные — 401, сработавший login_throttling — 429,
 * статусные отказы (деактивация) — 401 со своим сообщением.
 */
final readonly class AppAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['message' => 'Слишком много попыток входа. Попробуйте через минуту.', 'errors' => null],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        // Статус аккаунта — не секрет от его владельца: пароль уже верный,
        // enumeration это не открывает, а внятное сообщение экономит поддержку
        if ($exception instanceof AccountStatusException) {
            return new JsonResponse(
                ['message' => $exception->getMessage(), 'errors' => null],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new JsonResponse(
            ['message' => 'Неверный email или пароль.', 'errors' => null],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
