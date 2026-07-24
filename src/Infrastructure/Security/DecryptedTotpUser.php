<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\TotpSecretCipherInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

/**
 * Обёртка пользователя для scheb: сущность хранит и отдаёт ШИФРТЕКСТ
 * TOTP-секрета, здесь он подменяется расшифрованным — единственная точка,
 * где plaintext-секрет существует, и только на время проверки кода.
 */
final readonly class DecryptedTotpUser implements TwoFactorInterface
{
    public function __construct(
        private TwoFactorInterface $inner,
        private TotpSecretCipherInterface $cipher,
    ) {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->inner->isTotpAuthenticationEnabled();
    }

    public function getTotpAuthenticationUsername(): ?string
    {
        return $this->inner->getTotpAuthenticationUsername();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        $configuration = $this->inner->getTotpAuthenticationConfiguration();

        if ($configuration === null) {
            return null;
        }

        return new TotpConfiguration(
            $this->cipher->decrypt($configuration->getSecret()),
            $configuration->getAlgorithm(),
            $configuration->getPeriod(),
            $configuration->getDigits(),
        );
    }
}
