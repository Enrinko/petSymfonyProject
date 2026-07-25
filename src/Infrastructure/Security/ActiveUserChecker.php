<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Деактивированный аккаунт не аутентифицируется нигде: json_login,
 * remember-me кука и восстановление из сессии (ContextListener refresh)
 * проходят через один и тот же checker — активная сессия гаснет
 * при первом же запросе после деактивации.
 */
final class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) {
            // Ключ каталога: в HTTP-ответ его переведёт AppAuthenticationFailureHandler
            throw new CustomUserMessageAccountStatusException('auth.account.deactivated');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
