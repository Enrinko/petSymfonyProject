<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\User\RegisterUserCommand;
use App\Application\User\RegisterUserHandler;
use App\Application\User\VerificationMailerInterface;
use App\Domain\User\Exception\EmailAlreadyInUseException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(
        Request $request,
        ValidatorInterface $validator,
        RegisterUserHandler $handler,
        RateLimiterFactoryInterface $registrationIpLimiter,
        VerificationMailerInterface $verificationMailer,
        TranslatorInterface $translator,
    ): JsonResponse {
        $limit = $registrationIpLimiter->create($request->getClientIp() ?? 'unknown')->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new RegisterUserCommand(
            (string) ($payload['email'] ?? ''),
            (string) ($payload['password'] ?? ''),
            (string) ($payload['passwordConfirm'] ?? ''),
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $user = $handler($command);
        } catch (EmailAlreadyInUseException) {
            $taken = $translator->trans('auth.register.email_taken');

            return ApiJson::error($taken, Response::HTTP_CONFLICT, ['email' => $taken]);
        }

        // Письмо подтверждения уходит асинхронно; сбой очереди не ломает регистрацию
        $verificationMailer->sendVerificationLink($user);

        return $this->json(['message' => $translator->trans('auth.register.created')], Response::HTTP_CREATED);
    }
}
