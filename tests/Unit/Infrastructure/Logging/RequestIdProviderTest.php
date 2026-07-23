<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Logging;

use App\Infrastructure\Logging\RequestIdProvider;
use PHPUnit\Framework\TestCase;

final class RequestIdProviderTest extends TestCase
{
    public function testGeneratesIdLazily(): void
    {
        $provider = new RequestIdProvider();

        $id = $provider->get();

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id);
        self::assertSame($id, $provider->get(), 'Id стабилен в рамках запроса.');
    }

    public function testAcceptsValidInboundId(): void
    {
        $provider = new RequestIdProvider();

        $provider->accept('caddy-abc123-DEF');

        self::assertSame('caddy-abc123-DEF', $provider->get());
    }

    public function testRejectsMalformedInboundId(): void
    {
        $provider = new RequestIdProvider();

        // Log injection: перевод строки и спецсимволы не должны попасть в логи
        $provider->accept("evil\nid");

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $provider->get());
    }

    public function testRejectsTooShortAndTooLongIds(): void
    {
        $provider = new RequestIdProvider();

        $provider->accept('short');
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $provider->get());

        $provider->reset();
        $provider->accept(str_repeat('a', 65));
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $provider->get());
    }

    public function testResetIssuesFreshId(): void
    {
        // Worker-режим FrankenPHP: без reset id «протёк» бы в чужой запрос
        $provider = new RequestIdProvider();
        $first = $provider->get();

        $provider->reset();

        self::assertNotSame($first, $provider->get());
    }
}
