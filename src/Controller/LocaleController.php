<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Http\LocaleResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Переключатель языка. Гостю локаль пишется в сессию; залогиненному —
 * ещё и в профиль (чтобы предпочтение пережило logout и другие устройства).
 */
final class LocaleController extends AbstractController
{
    #[Route('/locale', name: 'app_locale_switch', methods: ['POST'])]
    public function __invoke(Request $request, UserRepositoryInterface $users): Response
    {
        // AccessDeniedHttpException, а не createAccessDeniedException(): security-исключение
        // для гостя ушло бы в entry point (redirect на /login), а это просто невалидный запрос
        if (!$this->isCsrfTokenValid('switch_locale', (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $locale = (string) $request->request->get('_locale');

        if (!\in_array($locale, LocaleResolver::SUPPORTED, true)) {
            throw new BadRequestHttpException('Unsupported locale.');
        }

        $request->getSession()->set(LocaleResolver::SESSION_KEY, $locale);

        $user = $this->getUser();

        if ($user instanceof User) {
            $user->changeLocale($locale);
            $users->save($user);
        }

        // Возврат на страницу, с которой переключали; чужие Referer не редиректим
        $referer = $request->headers->get('referer');
        $target = \is_string($referer) && str_starts_with($referer, $request->getSchemeAndHttpHost())
            ? $referer
            : '/';

        return $this->redirect($target, Response::HTTP_SEE_OTHER);
    }
}
