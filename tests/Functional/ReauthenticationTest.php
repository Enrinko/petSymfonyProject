<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\Role;

/**
 * Повторная аутентификация REMEMBERED-пользователя: страница /login обязана
 * показать форму (а не отфутболить на главную — это давало петлю
 * «/admin → /login → /», из-за которой админка «не открывалась»),
 * а после подтверждения пароля вернуть в запрошенный раздел.
 */
final class ReauthenticationTest extends FunctionalTestCase
{
    public function testRememberedUserSeesLoginFormInsteadOfRedirect(): void
    {
        $this->becomeRemembered();

        $this->client->request('GET', '/login');

        self::assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'REMEMBERED-пользователь должен увидеть форму подтверждения пароля, а не редирект.',
        );
    }

    public function testFullyAuthenticatedUserIsStillRedirectedHome(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/login');

        self::assertTrue($this->client->getResponse()->isRedirect('/'));
    }

    public function testReauthenticationReturnsToRequestedAdminPage(): void
    {
        $user = $this->becomeRemembered(admin: true);

        // Попытка входа в админку: недостаточно REMEMBERED → на /login
        $this->client->request('GET', '/admin/users');
        self::assertTrue($this->client->getResponse()->isRedirect('/login'));

        // Форма открывается, предзаполнена email'ом и вернёт в админку
        $this->client->request('GET', '/login');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString($user->getEmail(), $html);
        self::assertStringContainsString('\/admin\/users', $html, 'redirectUrl должен вести обратно в админку.');

        // Подтверждение пароля возвращает полную аутентификацию
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/admin/users');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    private function becomeRemembered(bool $admin = false): \App\Domain\User\User
    {
        $user = $this->createUser(roles: $admin ? [Role::Admin->value] : []);

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
            '_remember_me' => true,
        ]);

        $jar = $this->client->getCookieJar();

        foreach ($jar->all() as $cookie) {
            if ($cookie->getName() !== 'REMEMBERME') {
                $jar->expire($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        return $user;
    }
}
