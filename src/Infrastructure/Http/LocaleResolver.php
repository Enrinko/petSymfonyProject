<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\User\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Локаль интерфейса: параметр пользователя → сессия → Accept-Language → ru.
 *
 * hasPreviousSession() вместо безусловного чтения: иначе гость без куки
 * получал бы стартовавшую Redis-сессию на каждый запрос.
 */
final readonly class LocaleResolver
{
    public const array SUPPORTED = ['ru', 'en'];
    public const string SESSION_KEY = '_locale';

    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function resolve(Request $request): string
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user instanceof User && \in_array($user->getLocale(), self::SUPPORTED, true)) {
            return $user->getLocale();
        }

        if ($request->hasPreviousSession()) {
            $sessionLocale = $request->getSession()->get(self::SESSION_KEY);

            if (\in_array($sessionLocale, self::SUPPORTED, true)) {
                return $sessionLocale;
            }
        }

        return $request->getPreferredLanguage(self::SUPPORTED) ?? 'ru';
    }
}
