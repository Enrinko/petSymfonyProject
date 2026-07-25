<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Infrastructure\Http\LocaleResolver;
use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * JSON-обработчики флоу 2FA: фронтенд разговаривает конвертами, а не
 * редиректами. Один класс на три роли — они по строчке каждая.
 *
 * - required: пароль верен, ждём код → {twoFactorRequired: true}
 * - success: код верен → 200, фронт делает обычный пост-логин редирект
 * - failure: код неверен → 401 конверт (+audit), перебор попыток → 429
 *
 * Локаль резолвим явно: хендлеры срабатывают до LocaleRequestListener.
 */
final readonly class TwoFactorJsonHandlers implements
    AuthenticationRequiredHandlerInterface,
    AuthenticationSuccessHandlerInterface,
    AuthenticationFailureHandlerInterface
{
    public function __construct(
        private AuditLoggerInterface $audit,
        private TranslatorInterface $translator,
        private LocaleResolver $localeResolver,
    ) {
    }

    public function onAuthenticationRequired(Request $request, TokenInterface $token): Response
    {
        // Частично аутентифицированный полез на защищённый путь:
        // API — 401 конверт (httpClient не примет за успех), HTML — на логин
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return new JsonResponse(
                [
                    'message' => $this->translator->trans('auth.2fa.required', [], null, $this->localeResolver->resolve($request)),
                    'errors' => null,
                ],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new RedirectResponse('/login');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        return new JsonResponse(['message' => 'ok']);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $locale = $this->localeResolver->resolve($request);

        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['message' => $this->translator->trans('auth.2fa.throttled', [], null, $locale), 'errors' => null],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $this->audit->log(AuditAction::TwoFactorFailed, null, null, ['stage' => 'login']);

        return new JsonResponse(
            ['message' => $this->translator->trans('auth.2fa.invalid_code', [], null, $locale), 'errors' => null],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
