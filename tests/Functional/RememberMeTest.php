<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\Role;
use App\Domain\User\User;

/**
 * «Запомнить меня»: подписанная кука по флагу _remember_me в json_login,
 * восстановление сессии из куки — но только до уровня REMEMBERED:
 * админка требует полной аутентификации (защита от угнанной куки).
 */
final class RememberMeTest extends FunctionalTestCase
{
    private const string COOKIE = 'REMEMBERME';

    public function testLoginWithFlagSetsRememberMeCookie(): void
    {
        $user = $this->createUser();

        $this->login($user, rememberMe: true);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertNotNull(
            $this->client->getCookieJar()->get(self::COOKIE),
            'После входа с флагом ожидали куку REMEMBERME.',
        );
    }

    public function testLoginWithoutFlagSetsNoCookie(): void
    {
        $user = $this->createUser();

        $this->login($user, rememberMe: false);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        self::assertNull($this->client->getCookieJar()->get(self::COOKIE));
    }

    public function testLogoutClearsRememberMeCookie(): void
    {
        $this->login($this->createUser(), rememberMe: true);
        self::assertNotNull($this->client->getCookieJar()->get(self::COOKIE));

        $this->client->request('GET', '/logout');
        self::assertTrue($this->client->getResponse()->isRedirection());

        self::assertNull(
            $this->client->getCookieJar()->get(self::COOKIE),
            'Logout обязан гасить remember-куку.',
        );
    }

    public function testRememberedCookieRestoresSessionForRegularPages(): void
    {
        $this->login($this->createUser(), rememberMe: true);
        $this->dropSessionCookie();

        // Сессии нет — но кука восстанавливает аутентификацию
        $this->client->request('GET', '/clients');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testRememberedIsNotEnoughForAdmin(): void
    {
        $admin = $this->createUser(roles: [Role::Admin->value]);

        $this->login($admin, rememberMe: true);

        // С живой сессией (полная аутентификация) админка доступна
        $this->jsonRequest('GET', '/api/admin/users');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->dropSessionCookie();

        // Из remember-куки — только IS_AUTHENTICATED_REMEMBERED: админке мало.
        // Symfony отвечает не 403, а 401 через entry point — «войдите полноценно»
        // (httpClient на 401 уводит на /login — ровно нужный UX)
        $this->jsonRequest('GET', '/api/admin/users');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        // При этом обычные страницы жить продолжают
        $this->client->request('GET', '/clients');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    private function login(User $user, bool $rememberMe): void
    {
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
            '_remember_me' => $rememberMe,
        ]);
    }

    private function dropSessionCookie(): void
    {
        $jar = $this->client->getCookieJar();

        foreach ($jar->all() as $cookie) {
            if ($cookie->getName() !== self::COOKIE) {
                $jar->expire($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }
    }
}
