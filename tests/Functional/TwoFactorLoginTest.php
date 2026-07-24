<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\User;
use OTPHP\TOTP;

/**
 * Флоу входа с 2FA: пароль → частичное состояние → код → полный доступ.
 */
final class TwoFactorLoginTest extends FunctionalTestCase
{
    public function testLoginRequiresSecondFactorAndCodeCompletesIt(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();

        // Пароль верен → ждём второй фактор
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['twoFactorRequired']);

        // В частичном состоянии API закрыт
        $this->jsonRequest('GET', '/api/profile');
        self::assertResponseStatusCodeSame(401);

        // Верный код → полный доступ
        $this->jsonRequest('POST', '/2fa_check', ['_auth_code' => $this->totpNow($secret)]);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/profile');
        self::assertResponseIsSuccessful();
        self::assertSame($user->getEmail(), $this->json()['email']);
    }

    public function testWrongCodeIsRejected(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);

        $this->jsonRequest('POST', '/2fa_check', ['_auth_code' => '000000']);

        self::assertResponseStatusCodeSame(401);
        $this->jsonRequest('GET', '/api/profile');
        self::assertResponseStatusCodeSame(401);
    }

    public function testBackupCodeWorksOnce(): void
    {
        [$user, , $backupCodes] = $this->userWithTwoFactor();
        $backup = $backupCodes[0];

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        $this->jsonRequest('POST', '/2fa_check', ['_auth_code' => $backup]);
        self::assertResponseIsSuccessful();

        // Выходим и пробуем тот же backup-код второй раз — он одноразовый
        $this->client->request('GET', '/logout');
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        $this->jsonRequest('POST', '/2fa_check', ['_auth_code' => $backup]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCodeAttemptsAreThrottled(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);

        for ($i = 0; $i < 5; ++$i) {
            $this->jsonRequest('POST', '/2fa_check', ['_auth_code' => '000000']);
            self::assertResponseStatusCodeSame(401);
        }

        $this->jsonRequest('POST', '/2fa_check', ['_auth_code' => '000000']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testUserWithoutTwoFactorLogsInDirectly(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);

        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('twoFactorRequired', $this->json());

        $this->jsonRequest('GET', '/api/profile');
        self::assertResponseIsSuccessful();
    }

    /**
     * Готовит пользователя с включённой 2FA (через API, затем логаут).
     *
     * @return array{0: User, 1: string, 2: list<string>}
     */
    private function userWithTwoFactor(): array
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        $this->jsonRequest('POST', '/api/profile/2fa/setup');
        $secret = (string) $this->json()['secret'];
        $this->jsonRequest('POST', '/api/profile/2fa/enable', ['code' => $this->totpNow($secret)]);
        self::assertResponseIsSuccessful();
        /** @var list<string> $backupCodes */
        $backupCodes = $this->json()['backupCodes'];

        $this->client->request('GET', '/logout');

        return [$user, $secret, $backupCodes];
    }

    private function totpNow(string $secret): string
    {
        \assert($secret !== '');

        return TOTP::create($secret)->now();
    }
}
