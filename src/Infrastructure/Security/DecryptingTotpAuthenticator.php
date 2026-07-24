<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\TotpSecretCipherInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

/**
 * Декоратор scheb-аутентификатора: перед проверкой кода/генерацией QR
 * подсовывает пользователя с расшифрованным секретом. Декорирует сервис
 * scheb_two_factor.security.totp_authenticator (см. services.yaml) —
 * покрывает и флоу входа, и API профиля.
 */
final readonly class DecryptingTotpAuthenticator implements TotpAuthenticatorInterface
{
    public function __construct(
        private TotpAuthenticatorInterface $inner,
        private TotpSecretCipherInterface $cipher,
    ) {
    }

    public function checkCode(TwoFactorInterface $user, string $code): bool
    {
        return $this->inner->checkCode(new DecryptedTotpUser($user, $this->cipher), $code);
    }

    public function getQRContent(TwoFactorInterface $user): string
    {
        return $this->inner->getQRContent(new DecryptedTotpUser($user, $this->cipher));
    }

    public function generateSecret(): string
    {
        return $this->inner->generateSecret();
    }
}
