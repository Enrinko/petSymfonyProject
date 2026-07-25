<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * POST /locale: гость получает липкую локаль в сессии, залогиненный —
 * ещё и сохранённое предпочтение в профиле. CSRF-токен берём из реальной
 * формы переключателя на auth-странице.
 */
final class LocaleSwitchTest extends FunctionalTestCase
{
    public function testGuestSwitchSticksInSession(): void
    {
        $token = $this->switcherToken('/login');

        $this->client->request('POST', '/locale', [
            '_locale' => 'en',
            '_token' => $token,
        ], [], ['HTTP_REFERER' => 'http://localhost/login']);

        self::assertSame(303, $this->client->getResponse()->getStatusCode());
        self::assertTrue($this->client->getResponse()->isRedirect('http://localhost/login'));

        $crawler = $this->client->followRedirect();

        self::assertSame('en', $crawler->filter('html')->attr('lang'));
    }

    public function testAuthenticatedSwitchPersistsUserPreference(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        // /forgot-password не редиректит залогиненных — форма переключателя доступна
        $token = $this->switcherToken('/forgot-password');

        $this->client->request('POST', '/locale', [
            '_locale' => 'en',
            '_token' => $token,
        ], [], ['HTTP_REFERER' => 'http://localhost/forgot-password']);

        self::assertSame(303, $this->client->getResponse()->getStatusCode());

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $reloaded = $em->find(User::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertSame('en', $reloaded->getLocale());
    }

    public function testUnsupportedLocaleIsRejected(): void
    {
        $token = $this->switcherToken('/login');

        $this->client->request('POST', '/locale', [
            '_locale' => 'de',
            '_token' => $token,
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $this->client->request('POST', '/locale', [
            '_locale' => 'en',
            '_token' => 'not-a-token',
        ]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    /** CSRF-токен из скрытого поля формы переключателя на странице. */
    private function switcherToken(string $page): string
    {
        $crawler = $this->client->request('GET', $page);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $token = $crawler->filter('form.locale-switch input[name="_token"]')->attr('value');
        self::assertNotNull($token, 'На странице нет формы переключателя локали.');

        return $token;
    }
}
