<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Infrastructure\Http\LocaleResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * API-запросы без аутентификации получают 401 JSON,
 * страницы — редирект на форму входа.
 *
 * Локаль резолвим явно: entry point срабатывает до LocaleRequestListener.
 */
final readonly class AppAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private LocaleResolver $localeResolver,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api')) {
            $locale = $this->localeResolver->resolve($request);

            return new JsonResponse(
                ['message' => $this->translator->trans('auth.required', [], null, $locale), 'errors' => null],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
