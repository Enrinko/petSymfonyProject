<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\User;
use OTPHP\TOTP;

/**
 * API 2FA профиля: setup → enable (код подтверждения) → disable.
 * Валидные коды генерируются напрямую из секрета (otphp), как это
 * делает приложение-аутентификатор.
 */
final class TwoFactorApiTest extends FunctionalTestCase
{
    public function testSetupEnableDisableHappyPath(): void
    {
        $user = $this->createUser();
        $this->login($user);

        // Setup: секрет + otpauth-URI
        $this->jsonRequest('POST', '/api/profile/2fa/setup');
        self::assertResponseIsSuccessful();
        $setup = $this->json();
        self::assertArrayHasKey('secret', $setup);
        self::assertStringStartsWith('otpauth://totp/', (string) $setup['otpauthUri']);

        // Enable с валидным кодом — как из приложения-аутентификатора
        $code = $this->totpNow((string) $setup['secret']);
        $this->jsonRequest('POST', '/api/profile/2fa/enable', ['code' => $code]);
        self::assertResponseIsSuccessful();
        $enabled = $this->json();
        self::assertCount(8, $enabled['backupCodes']);

        // Профиль отражает включённую 2FA
        $this->jsonRequest('GET', '/api/profile');
        self::assertTrue($this->json()['totpEnabled']);

        // Disable: пароль + backup-код
        $backupCode = $enabled['backupCodes'][0];
        $this->jsonRequest('POST', '/api/profile/2fa/disable', [
            'currentPassword' => self::STRONG_PASSWORD,
            'code' => $backupCode,
        ]);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/profile');
        self::assertFalse($this->json()['totpEnabled']);
    }

    public function testEnableWithGarbageCodeIsRejected(): void
    {
        $user = $this->createUser();
        $this->login($user);

        $this->jsonRequest('POST', '/api/profile/2fa/setup');
        $this->jsonRequest('POST', '/api/profile/2fa/enable', ['code' => '000000']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('code', $this->json()['errors']);
    }

    public function testSetupWhenAlreadyEnabledConflicts(): void
    {
        $user = $this->createUser();
        $this->login($user);
        $this->enableTwoFactor($user);

        $this->jsonRequest('POST', '/api/profile/2fa/setup');

        self::assertResponseStatusCodeSame(409);
    }

    public function testDisableRequiresCorrectPassword(): void
    {
        $user = $this->createUser();
        $this->login($user);
        $secret = $this->enableTwoFactor($user);

        $this->jsonRequest('POST', '/api/profile/2fa/disable', [
            'currentPassword' => 'wrong-password-42',
            'code' => $this->totpNow($secret),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('currentPassword', $this->json()['errors']);
    }

    private function login(User $user): void
    {
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertResponseIsSuccessful();
    }

    /** Включает 2FA через API, возвращает plaintext-секрет. */
    private function enableTwoFactor(User $user): string
    {
        $this->jsonRequest('POST', '/api/profile/2fa/setup');
        $secret = (string) $this->json()['secret'];

        $this->jsonRequest('POST', '/api/profile/2fa/enable', [
            'code' => $this->totpNow($secret),
        ]);
        self::assertResponseIsSuccessful();

        return $secret;
    }

    /** Код «как из приложения-аутентификатора» для текущего окна. */
    private function totpNow(string $secret): string
    {
        \assert($secret !== '');

        return TOTP::create($secret)->now();
    }
}
