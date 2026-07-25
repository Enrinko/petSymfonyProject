<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\BackupCode;
use App\Domain\User\BackupCodeRepositoryInterface;
use App\Domain\User\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Backup\BackupCodeManagerInterface;

/**
 * Менеджер резервных кодов для scheb + генерация набора.
 * Коды 10 цифр, хранятся bcrypt-хэшами, одноразовые.
 */
final readonly class BackupCodeManager implements BackupCodeManagerInterface
{
    public const int CODES_COUNT = 8;
    private const int CODE_DIGITS = 10;

    public function __construct(
        private BackupCodeRepositoryInterface $codes,
    ) {
    }

    public function isBackupCode(object $user, string $code): bool
    {
        return $user instanceof User && $this->findMatching($user, $code) !== null;
    }

    public function invalidateBackupCode(object $user, string $code): void
    {
        if (!$user instanceof User) {
            return;
        }

        $matching = $this->findMatching($user, $code);

        if ($matching !== null) {
            $matching->markUsed();
            $this->codes->save($matching);
        }
    }

    /**
     * Сгенерировать свежий набор (старые коды стираются).
     *
     * @return list<string> plaintext-коды — показываются пользователю ОДИН раз
     */
    public function regenerate(User $user): array
    {
        $this->codes->removeAllForUser($user);

        $plainCodes = [];

        while (\count($plainCodes) < self::CODES_COUNT) {
            $code = str_pad((string) random_int(0, 10 ** self::CODE_DIGITS - 1), self::CODE_DIGITS, '0', STR_PAD_LEFT);

            if (\in_array($code, $plainCodes, true)) {
                continue; // коллизия — перегенерировать
            }

            $plainCodes[] = $code;
            $this->codes->save(new BackupCode($user, password_hash($code, PASSWORD_BCRYPT)));
        }

        return $plainCodes;
    }

    public function removeAll(User $user): void
    {
        $this->codes->removeAllForUser($user);
    }

    private function findMatching(User $user, string $code): ?BackupCode
    {
        foreach ($this->codes->findActiveByUser($user) as $candidate) {
            if ($candidate->matches($code)) {
                return $candidate;
            }
        }

        return null;
    }
}
