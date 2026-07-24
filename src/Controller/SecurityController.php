<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SecurityController extends AbstractController
{
    /**
     * Редирект на главную — только для ПОЛНОЙ аутентификации.
     * REMEMBERED-пользователь (вернулся по remember-куке) обязан увидеть форму:
     * иначе «/admin → /login → /» замыкается в петлю и админка «не открывается».
     */
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(Request $request): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_home');
        }

        $user = $this->getUser();
        // Куда вернуть после входа: ExceptionListener кладёт сюда страницу,
        // с которой отправили на re-login (например, /admin/users)
        $targetPath = $request->getSession()->get('_security.main.target_path');

        return $this->render('security/login.html.twig', [
            'prefillEmail' => $user instanceof User ? $user->getEmail() : null,
            'targetPath' => \is_string($targetPath) ? $targetPath : null,
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function register(): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/register.html.twig');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET'])]
    public function forgotPassword(): Response
    {
        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET'])]
    public function resetPassword(string $token): Response
    {
        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }

    /**
     * POST обрабатывает json_login; сюда запрос доходит уже аутентифицированным.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function apiLogin(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(
                ['message' => 'Запрос должен содержать JSON с полями email и password.', 'errors' => null],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // Пароль верен, но включена 2FA: scheb перевёл токен в частичное
        // состояние — фронтенд показывает шаг ввода кода
        if ($this->isGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS')) {
            return $this->json(['twoFactorRequired' => true]);
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Перехватывается ключом logout файрвола — сюда выполнение не доходит.');
    }
}
