<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\SodiumTotpSecretCipher;
use PHPUnit\Framework\TestCase;

final class SodiumTotpSecretCipherTest extends TestCase
{
    private SodiumTotpSecretCipher $cipher;

    protected function setUp(): void
    {
        $this->cipher = new SodiumTotpSecretCipher(base64_encode(random_bytes(32)));
    }

    public function testRoundtrip(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        $encrypted = $this->cipher->encrypt($secret);

        self::assertNotSame($secret, $encrypted);
        self::assertSame($secret, $this->cipher->decrypt($encrypted));
    }

    public function testNonceMakesCiphertextsDiffer(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        self::assertNotSame($this->cipher->encrypt($secret), $this->cipher->encrypt($secret));
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $encrypted = $this->cipher->encrypt('JBSWY3DPEHPK3PXP');
        $bytes = base64_decode($encrypted, true);
        self::assertIsString($bytes);
        $bytes[strlen($bytes) - 1] = $bytes[strlen($bytes) - 1] === 'a' ? 'b' : 'a';

        $this->expectException(\RuntimeException::class);
        $this->cipher->decrypt(base64_encode($bytes));
    }

    public function testWrongKeyIsRejected(): void
    {
        $other = new SodiumTotpSecretCipher(base64_encode(random_bytes(32)));
        $encrypted = $this->cipher->encrypt('JBSWY3DPEHPK3PXP');

        $this->expectException(\RuntimeException::class);
        $other->decrypt($encrypted);
    }

    public function testInvalidKeyLengthIsRejectedUpfront(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SodiumTotpSecretCipher(base64_encode('short'));
    }
}
