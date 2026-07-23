<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\AppAuthenticationFailureHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

final class AppAuthenticationFailureHandlerTest extends TestCase
{
    public function testBadCredentialsGive401Envelope(): void
    {
        $response = new AppAuthenticationFailureHandler(new NullLogger())
            ->onAuthenticationFailure(Request::create('/api/login', 'POST'), new BadCredentialsException());

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Неверный email или пароль.', 'errors' => null],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testThrottledLoginGives429Envelope(): void
    {
        $response = new AppAuthenticationFailureHandler(new NullLogger())->onAuthenticationFailure(
            Request::create('/api/login', 'POST'),
            new TooManyLoginAttemptsAuthenticationException(1),
        );

        self::assertSame(429, $response->getStatusCode());
        self::assertSame(
            ['message' => 'Слишком много попыток входа. Попробуйте через минуту.', 'errors' => null],
            json_decode((string) $response->getContent(), true),
        );
    }
}
