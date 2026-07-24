<?php

declare(strict_types=1);

namespace App\Domain\User;

/**
 * Порт шифрования TOTP-секретов. Секрет — ключ ко второму фактору,
 * в БД он лежит только шифртекстом; расшифровка — в момент проверки кода.
 */
interface TotpSecretCipherInterface
{
    public function encrypt(string $plaintext): string;

    /** @throws \RuntimeException при повреждённом шифртексте или чужом ключе */
    public function decrypt(string $ciphertext): string;
}
