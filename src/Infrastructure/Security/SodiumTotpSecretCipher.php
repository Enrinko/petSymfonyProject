<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\TotpSecretCipherInterface;

/**
 * Адаптер порта шифрования на libsodium (алгоритм XSalsa20-Poly1305,
 * аутентифицированное шифрование — подмена шифртекста детектируется).
 * Формат хранения: base64(nonce . ciphertext). Ключ — 32 байта base64
 * из env TOTP_ENCRYPTION_KEY (в проде обязателен, см. compose.prod.yaml).
 */
final readonly class SodiumTotpSecretCipher implements TotpSecretCipherInterface
{
    private string $key;

    public function __construct(string $base64Key)
    {
        $key = base64_decode($base64Key, true);

        if ($key === false || \strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException(sprintf(
                'TOTP_ENCRYPTION_KEY must be %d bytes, base64-encoded.',
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            ));
        }

        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);

        if ($decoded === false || \strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Malformed TOTP secret ciphertext.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $box = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($box, $nonce, $this->key);

        if ($plaintext === false) {
            throw new \RuntimeException('TOTP secret decryption failed (tampered data or wrong key).');
        }

        return $plaintext;
    }
}
