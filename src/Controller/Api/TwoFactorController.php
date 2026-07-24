<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Profile\InvalidTotpCodeException;
use App\Application\Profile\TwoFactorAlreadyEnabledException;
use App\Application\Profile\TwoFactorNotConfiguredException;
use App\Application\Profile\TwoFactorService;
use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {
    }

    #[Route('/api/profile/2fa/setup', name: 'api_2fa_setup', methods: ['POST'])]
    public function setup(#[CurrentUser] User $user): JsonResponse
    {
        try {
            return $this->json($this->twoFactor->setup($user));
        } catch (TwoFactorAlreadyEnabledException) {
            return ApiJson::error('Двухфакторная аутентификация уже включена.', Response::HTTP_CONFLICT);
        }
    }

    #[Route('/api/profile/2fa/enable', name: 'api_2fa_enable', methods: ['POST'])]
    public function enable(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        try {
            $backupCodes = $this->twoFactor->enable($user, (string) ($payload['code'] ?? ''));
        } catch (TwoFactorAlreadyEnabledException) {
            return ApiJson::error('Двухфакторная аутентификация уже включена.', Response::HTTP_CONFLICT);
        } catch (TwoFactorNotConfiguredException) {
            return ApiJson::error('Сначала запросите настройку 2FA.', Response::HTTP_CONFLICT);
        } catch (InvalidTotpCodeException) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['code' => 'Неверный код. Проверьте время на устройстве и попробуйте ещё раз.'],
            );
        }

        return $this->json([
            'message' => 'Двухфакторная аутентификация включена.',
            'backupCodes' => $backupCodes,
        ]);
    }

    #[Route('/api/profile/2fa/disable', name: 'api_2fa_disable', methods: ['POST'])]
    public function disable(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        try {
            $this->twoFactor->disable(
                $user,
                (string) ($payload['currentPassword'] ?? ''),
                (string) ($payload['code'] ?? ''),
            );
        } catch (TwoFactorNotConfiguredException) {
            return ApiJson::error('Двухфакторная аутентификация не включена.', Response::HTTP_CONFLICT);
        } catch (InvalidCurrentPasswordException) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['currentPassword' => 'Текущий пароль указан неверно.'],
            );
        } catch (InvalidTotpCodeException) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['code' => 'Неверный код (примите TOTP или резервный).'],
            );
        }

        return $this->json(['message' => 'Двухфакторная аутентификация отключена.']);
    }
}
