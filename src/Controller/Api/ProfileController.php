<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Profile\ChangePasswordCommand;
use App\Application\Profile\ChangePasswordHandler;
use App\Application\Profile\UpdateAvatarHandler;
use App\Application\Profile\UpdateProfileCommand;
use App\Application\Profile\UpdateProfileHandler;
use App\Domain\Session\UserSession;
use App\Domain\Session\UserSessionRepositoryInterface;
use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\Exception\UnsupportedAvatarImageException;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProfileController extends AbstractController
{
    private const int AVATAR_MAX_BYTES = 2 * 1024 * 1024;

    #[Route('/api/profile', name: 'api_profile_show', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->profilePayload($user));
    }

    #[Route('/api/profile', name: 'api_profile_update', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] User $user,
        Request $request,
        ValidatorInterface $validator,
        UpdateProfileHandler $handler,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        // PATCH точечный: не присланное поле сохраняет текущее значение,
        // иначе смена языка затирала бы имя (и наоборот)
        $displayName = \array_key_exists('displayName', $payload)
            ? ($payload['displayName'] === null ? null : (string) $payload['displayName'])
            : $user->getDisplayName();
        $locale = \array_key_exists('locale', $payload)
            ? ($payload['locale'] === null ? null : (string) $payload['locale'])
            : $user->getLocale();

        $command = new UpdateProfileCommand($displayName, $locale);

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        $handler($user, $command);

        return $this->json($this->profilePayload($user));
    }

    #[Route('/api/profile/password', name: 'api_profile_password', methods: ['POST'])]
    public function changePassword(
        #[CurrentUser] User $user,
        Request $request,
        ValidatorInterface $validator,
        ChangePasswordHandler $handler,
        UserSessionRepositoryInterface $sessions,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new ChangePasswordCommand(
            (string) ($payload['currentPassword'] ?? ''),
            (string) ($payload['newPassword'] ?? ''),
            (string) ($payload['newPasswordConfirm'] ?? ''),
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $handler($user, $command);
        } catch (InvalidCurrentPasswordException) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['currentPassword' => 'Текущий пароль указан неверно.'],
            );
        }

        // Смена пароля завершает все ПРОЧИЕ сессии: их PHP-сессии и так умрут
        // через EquatableInterface (password в isEqualTo), а записи списка —
        // здесь, чтобы профиль не показывал «живые» карточки мёртвых сессий
        $terminated = $sessions->removeAllForUserExcept(
            (int) $user->getId(),
            UserSession::hashOf($request->getSession()->getId()),
        );

        return $this->json(['message' => 'Пароль изменён.', 'terminatedSessions' => $terminated]);
    }

    #[Route('/api/profile/avatar', name: 'api_profile_avatar', methods: ['POST'])]
    public function uploadAvatar(
        #[CurrentUser] User $user,
        Request $request,
        UpdateAvatarHandler $handler,
    ): JsonResponse {
        $file = $request->files->get('avatar');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['avatar' => 'Прикрепите файл изображения (поле avatar).'],
            );
        }

        if ($file->getSize() > self::AVATAR_MAX_BYTES) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['avatar' => 'Файл больше 2 МБ.'],
            );
        }

        try {
            $handler($user, $file->getPathname());
        } catch (UnsupportedAvatarImageException) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['avatar' => 'Поддерживаются изображения JPEG, PNG или WebP.'],
            );
        }

        return $this->json($this->profilePayload($user));
    }

    #[Route('/api/profile/avatar', name: 'api_profile_avatar_delete', methods: ['DELETE'])]
    public function deleteAvatar(#[CurrentUser] User $user, UpdateAvatarHandler $handler): JsonResponse
    {
        $handler->remove($user);

        return $this->json($this->profilePayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        $avatarPath = $user->getAvatarPath();

        return [
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'initials' => $user->getInitials(),
            // Версия-метка сбрасывает кэш браузера после смены файла
            'avatarUrl' => $avatarPath === null ? null : $avatarPath . '?v=' . time(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'totpEnabled' => $user->isTotpEnabled(),
            'locale' => $user->getLocale(),
        ];
    }
}
