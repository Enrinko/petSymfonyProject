<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Http\LocaleResolver;
use App\Infrastructure\Security\AppAuthenticationFailureHandler;
use App\Tests\Fake\CatalogueTranslatorFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

final class AppAuthenticationFailureHandlerTest extends TestCase
{
    public function testBadCredentialsGive401Envelope(): void
    {
        $response = $this->handler()
            ->onAuthenticationFailure($this->ruRequest(), new BadCredentialsException());

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Неверный email или пароль.', 'errors' => null],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testThrottledLoginGives429Envelope(): void
    {
        $response = $this->handler()->onAuthenticationFailure(
            $this->ruRequest(),
            new TooManyLoginAttemptsAuthenticationException(1),
        );

        self::assertSame(429, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Слишком много попыток входа. Попробуйте через минуту.', 'errors' => null],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testAccountStatusMessageKeyIsTranslated(): void
    {
        $response = $this->handler()->onAuthenticationFailure(
            $this->ruRequest(),
            new CustomUserMessageAccountStatusException('auth.account.deactivated'),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Аккаунт деактивирован. Обратитесь к администратору.', 'errors' => null],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testLocaleFollowsAcceptLanguage(): void
    {
        $request = Request::create('/api/login', 'POST');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $response = $this->handler()->onAuthenticationFailure($request, new BadCredentialsException());

        $decoded = json_decode((string) $response->getContent(), true);
        self::assertIsArray($decoded);
        self::assertSame('Invalid email or password.', $decoded['message']);
    }

    /** Request::create по умолчанию шлёт Accept-Language: en-us — задаём русский явно. */
    private function ruRequest(): Request
    {
        $request = Request::create('/api/login', 'POST');
        $request->headers->set('Accept-Language', 'ru');

        return $request;
    }

    private function handler(): AppAuthenticationFailureHandler
    {
        return new AppAuthenticationFailureHandler(
            new NullLogger(),
            CatalogueTranslatorFactory::create(),
            new LocaleResolver(new TokenStorage()),
        );
    }
}
