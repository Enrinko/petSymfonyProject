<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\PasswordReset\RequestPasswordResetCommand;
use App\Application\PasswordReset\RequestPasswordResetHandler;
use App\Application\PasswordReset\ResetPasswordCommand;
use App\Application\PasswordReset\ResetPasswordHandler;
use App\Domain\PasswordReset\Exception\InvalidResetTokenException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/api/password/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgot(
        Request $request,
        RequestPasswordResetHandler $handler,
        RateLimiterFactoryInterface $passwordForgotIpLimiter,
        RateLimiterFactoryInterface $passwordForgotEmailLimiter,
    ): JsonResponse {
        $ipLimit = $passwordForgotIpLimiter->create($request->getClientIp() ?? 'unknown')->consume();

        if (!$ipLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $ipLimit->getRetryAfter()->getTimestamp() - time()));
        }

        $payload = $this->decode($request);

        if ($payload === null) {
            return $this->invalidJsonResponse();
        }

        $command = new RequestPasswordResetCommand((string) ($payload['email'] ?? ''));

        // Отдельный лимит на адрес: защита чужой почты от бомбардировки письмами.
        $emailLimit = $passwordForgotEmailLimiter
            ->create(mb_strtolower(trim($command->email)))
            ->consume();

        if (!$emailLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $emailLimit->getRetryAfter()->getTimestamp() - time()));
        }

        $violations = $this->validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        $handler($command);

        return $this->json(['message' => $this->translator->trans('auth.reset.requested')]);
    }

    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function reset(Request $request, ResetPasswordHandler $handler): JsonResponse
    {
        $payload = $this->decode($request);

        if ($payload === null) {
            return $this->invalidJsonResponse();
        }

        $command = new ResetPasswordCommand(
            (string) ($payload['token'] ?? ''),
            (string) ($payload['password'] ?? ''),
            (string) ($payload['passwordConfirm'] ?? ''),
        );

        $violations = $this->validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $handler($command);
        } catch (InvalidResetTokenException) {
            return ApiJson::error($this->translator->trans('auth.reset.invalid_token'), Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => $this->translator->trans('auth.reset.done')]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : null;
    }

    private function invalidJsonResponse(): JsonResponse
    {
        return ApiJson::invalidJson();
    }
}
