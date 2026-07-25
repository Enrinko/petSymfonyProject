<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\User\VerificationMailerInterface;
use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class VerifyEmailController extends AbstractController
{
    /**
     * Переход по ссылке из письма. Аноним допустим: письмо могут открыть
     * в браузере без активной сессии — подлинность гарантирует подпись.
     */
    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verify(
        Request $request,
        VerifyEmailHelperInterface $verifyEmailHelper,
        UserRepositoryInterface $users,
        AuditLoggerInterface $audit,
        TranslatorInterface $translator,
    ): Response {
        $userId = $request->query->getInt('id');
        $user = $userId > 0 ? $users->findById($userId) : null;

        if ($user === null) {
            return $this->render('security/verify_email_result.html.twig', [
                'success' => false,
                'reason' => $translator->trans('auth.verify.broken_link'),
            ]);
        }

        try {
            $verifyEmailHelper->validateEmailConfirmationFromRequest(
                $request,
                (string) $user->getId(),
                $user->getEmail(),
            );
        } catch (VerifyEmailExceptionInterface $exception) {
            return $this->render('security/verify_email_result.html.twig', [
                'success' => false,
                'reason' => $exception->getReason(),
            ]);
        }

        if (!$user->isVerified()) {
            $user->markVerified();
            $users->save($user);
            $audit->log(AuditAction::EmailVerified, 'user', (string) $user->getId());
        }

        return $this->render('security/verify_email_result.html.twig', [
            'success' => true,
            'reason' => null,
        ]);
    }

    /** Повторная отправка письма — из плашки в интерфейсе. */
    #[Route('/api/verify-email/resend', name: 'api_verify_email_resend', methods: ['POST'])]
    public function resend(
        #[CurrentUser] User $user,
        VerificationMailerInterface $mailer,
        RateLimiterFactoryInterface $verifyEmailResendLimiter,
        TranslatorInterface $translator,
    ): JsonResponse {
        if ($user->isVerified()) {
            return $this->json(['message' => $translator->trans('auth.verify.already')]);
        }

        $limit = $verifyEmailResendLimiter->create((string) $user->getId())->consume();

        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['message' => $translator->trans('auth.verify.throttled'), 'errors' => null],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $mailer->sendVerificationLink($user);

        return $this->json(['message' => $translator->trans('auth.verify.sent')]);
    }
}
