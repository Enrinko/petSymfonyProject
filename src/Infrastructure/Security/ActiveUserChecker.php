<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Деактивированный аккаунт не аутентифицируется нигде: json_login и
 * remember-me проходят проверку в checkPostAuth (после сверки пароля/куки),
 * а восстановление из сессии гасится EquatableInterface::isEqualTo
 * (там active входит в сравнение) — активная сессия отмирает при первом
 * же запросе после деактивации.
 *
 * Почему checkPostAuth, а не checkPreAuth: checkPreAuth вызывается ДО
 * проверки пароля (UserCheckerListener, приоритет 256 на CheckPassportEvent),
 * поэтому статусное сообщение там утекало бы на любой email без пароля
 * (user/status enumeration). В checkPostAuth (AuthenticationSuccessEvent)
 * его увидит только тот, кто уже доказал знание пароля.
 */
final class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($user instanceof User && !$user->isActive()) {
            // Ключ каталога: в HTTP-ответ его переведёт AppAuthenticationFailureHandler
            throw new CustomUserMessageAccountStatusException('auth.account.deactivated');
        }
    }
}
