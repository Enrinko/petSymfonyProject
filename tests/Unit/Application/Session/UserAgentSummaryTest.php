<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session;

use App\Application\Session\UserAgentSummary;
use PHPUnit\Framework\TestCase;

final class UserAgentSummaryTest extends TestCase
{
    public function testChromeOnWindows(): void
    {
        $summary = UserAgentSummary::parse(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        );

        self::assertSame('Chrome', $summary->browser);
        self::assertSame('Windows', $summary->os);
    }

    public function testFirefoxOnLinux(): void
    {
        $summary = UserAgentSummary::parse(
            'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0',
        );

        self::assertSame('Firefox', $summary->browser);
        self::assertSame('Linux', $summary->os);
    }

    public function testSafariOnMac(): void
    {
        $summary = UserAgentSummary::parse(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
        );

        self::assertSame('Safari', $summary->browser);
        self::assertSame('macOS', $summary->os);
    }

    public function testEdgeIsNotMistakenForChrome(): void
    {
        // UA Edge содержит и "Chrome", и "Safari" — порядок проверки важен
        $summary = UserAgentSummary::parse(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0',
        );

        self::assertSame('Edge', $summary->browser);
    }

    public function testOperaIsNotMistakenForChrome(): void
    {
        $summary = UserAgentSummary::parse(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/112.0.0.0',
        );

        self::assertSame('Opera', $summary->browser);
    }

    public function testMobileOs(): void
    {
        $android = UserAgentSummary::parse(
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
        );
        self::assertSame('Android', $android->os);

        $ios = UserAgentSummary::parse(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        );
        self::assertSame('iOS', $ios->os);
    }

    public function testUnknownAndNull(): void
    {
        $summary = UserAgentSummary::parse('curl/8.5.0');
        self::assertSame('Неизвестный браузер', $summary->browser);

        $empty = UserAgentSummary::parse(null);
        self::assertSame('Неизвестный браузер', $empty->browser);
        self::assertSame('', $empty->os);
    }
}
