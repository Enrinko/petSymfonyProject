<?php

declare(strict_types=1);

namespace App\Application\Profile;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\User\Exception\InvalidCurrentPasswordException;
use App\Domain\User\TotpSecretCipherInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Security\BackupCodeManager;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Сценарии 2FA профиля: setup → enable (подтверждение кодом) → disable.
 * Секрет сохраняется сразу при setup, но фактор включается только после
 * подтверждения кода — недоделанная привязка не блокирует вход.
 */
final readonly class TwoFactorService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private TotpAuthenticatorInterface $totp,
        private TotpSecretCipherInterface $cipher,
        private BackupCodeManager $backupCodes,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private AuditLoggerInterface $audit,
    ) {
    }

    /**
     * @return array{secret: string, otpauthUri: string}
     *
     * @throws TwoFactorAlreadyEnabledException
     */
    public function setup(User $user): array
    {
        if ($user->isTotpEnabled()) {
            throw new TwoFactorAlreadyEnabledException();
        }

        $secret = $this->totp->generateSecret();
        $user->setupTotp($this->cipher->encrypt($secret));
        $this->users->save($user);

        // QR-контент собирает scheb (issuer/период из конфига); секрет
        // возвращаем и текстом — для ручного ввода в аутентификатор
        return [
            'secret' => $secret,
            'otpauthUri' => $this->totp->getQRContent($user),
        ];
    }

    /**
     * @return list<string> backup-коды (показываются один раз)
     *
     * @throws TwoFactorAlreadyEnabledException|TwoFactorNotConfiguredException|InvalidTotpCodeException
     */
    public function enable(User $user, string $code): array
    {
        if ($user->isTotpEnabled()) {
            throw new TwoFactorAlreadyEnabledException();
        }

        if ($user->getTotpSecretCiphertext() === null) {
            throw new TwoFactorNotConfiguredException();
        }

        if (!$this->totp->checkCode($user, $code)) {
            $this->audit->log(AuditAction::TwoFactorFailed, 'user', (string) $user->getId(), ['stage' => 'enable']);

            throw new InvalidTotpCodeException();
        }

        $user->enableTotp();
        $this->users->save($user);

        $codes = $this->backupCodes->regenerate($user);
        $this->audit->log(AuditAction::TwoFactorEnabled, 'user', (string) $user->getId());

        return $codes;
    }

    /**
     * Отключение — только с текущим паролем и валидным кодом (TOTP или backup).
     *
     * @throws InvalidCurrentPasswordException|InvalidTotpCodeException|TwoFactorNotConfiguredException
     */
    public function disable(User $user, string $currentPassword, string $code): void
    {
        if (!$user->isTotpEnabled()) {
            throw new TwoFactorNotConfiguredException();
        }

        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);

        if (!$hasher->verify($user->getPassword(), $currentPassword)) {
            throw new InvalidCurrentPasswordException('Current password does not match.');
        }

        $validTotp = $this->totp->checkCode($user, $code);
        $validBackup = !$validTotp && $this->backupCodes->isBackupCode($user, $code);

        if (!$validTotp && !$validBackup) {
            $this->audit->log(AuditAction::TwoFactorFailed, 'user', (string) $user->getId(), ['stage' => 'disable']);

            throw new InvalidTotpCodeException();
        }

        $user->disableTotp();
        $this->users->save($user);
        $this->backupCodes->removeAll($user);
        $this->audit->log(AuditAction::TwoFactorDisabled, 'user', (string) $user->getId());
    }
}
