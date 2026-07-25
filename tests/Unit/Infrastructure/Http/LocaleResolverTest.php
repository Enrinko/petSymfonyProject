<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http;

use App\Domain\User\User;
use App\Infrastructure\Http\LocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class LocaleResolverTest extends TestCase
{
    public function testUserPreferenceWins(): void
    {
        $user = User::register('teacher@example.com', 'hash');
        $user->changeLocale('en');

        $request = $this->requestWithSession(sessionLocale: 'ru');

        self::assertSame('en', $this->resolver($user)->resolve($request));
    }

    public function testUserWithoutPreferenceFallsBackToSession(): void
    {
        $user = User::register('teacher@example.com', 'hash');

        $request = $this->requestWithSession(sessionLocale: 'en');

        self::assertSame('en', $this->resolver($user)->resolve($request));
    }

    public function testGuestUsesSessionLocale(): void
    {
        $request = $this->requestWithSession(sessionLocale: 'en');

        self::assertSame('en', $this->resolver(null)->resolve($request));
    }

    public function testGarbageSessionLocaleIsIgnored(): void
    {
        $request = $this->requestWithSession(sessionLocale: 'de');

        self::assertSame('ru', $this->resolver(null)->resolve($request));
    }

    public function testGuestWithoutSessionUsesAcceptLanguage(): void
    {
        $request = new Request();
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        self::assertSame('en', $this->resolver(null)->resolve($request));
    }

    public function testDefaultsToRu(): void
    {
        self::assertSame('ru', $this->resolver(null)->resolve(new Request()));
    }

    private function resolver(?User $user): LocaleResolver
    {
        $tokenStorage = new TokenStorage();

        if ($user !== null) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));
        }

        return new LocaleResolver($tokenStorage);
    }

    /** Сессия «существовавшая ранее»: hasPreviousSession() требует куку с именем сессии. */
    private function requestWithSession(string $sessionLocale): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(LocaleResolver::SESSION_KEY, $sessionLocale);

        $request = new Request();
        $request->setSession($session);
        $request->cookies->set($session->getName(), 'previous-session-id');

        return $request;
    }
}
