<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Брутфорс-защита второго фактора: лимит попыток кода до его проверки.
 * Ключ — IP + идентификатор пользователя (login_throttling до 2FA-шага
 * не достаёт: пароль уже верен). Исключение ловит failure-хендлер → 429.
 */
final readonly class TwoFactorAttemptThrottle
{
    public function __construct(
        private RateLimiterFactoryInterface $twoFactorAttemptLimiter,
    ) {
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::ATTEMPT)]
    public function onAttempt(TwoFactorAuthenticationEvent $event): void
    {
        $key = sprintf(
            '%s|%s',
            $event->getRequest()->getClientIp() ?? 'unknown',
            $event->getToken()->getUserIdentifier(),
        );

        if (!$this->twoFactorAttemptLimiter->create($key)->consume()->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException();
        }
    }
}
