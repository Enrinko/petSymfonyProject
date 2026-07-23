<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Session;

use App\Domain\Session\UserSession;
use PHPUnit\Framework\TestCase;

final class UserSessionTest extends TestCase
{
    public function testOpenTruncatesLongUserAgentAndIp(): void
    {
        $session = UserSession::open('hash', 7, str_repeat('a', 300), str_repeat('1', 60));

        self::assertSame(255, mb_strlen((string) $session->getUserAgent()));
        self::assertSame(45, mb_strlen((string) $session->getIp()));
        self::assertSame(7, $session->getUserId());
    }

    public function testTouchThrottlesWrites(): void
    {
        $session = UserSession::open('hash', 7, null, null);
        $created = $session->getLastSeenAt();

        // Спустя 30 секунд — рано, записи нет
        self::assertFalse($session->touch($created->modify('+30 seconds')));
        self::assertSame($created, $session->getLastSeenAt());

        // Спустя 61 секунду — пора
        $later = $created->modify('+61 seconds');
        self::assertTrue($session->touch($later));
        self::assertSame($later, $session->getLastSeenAt());
    }

    public function testHashOfIsStableSha256(): void
    {
        self::assertSame(hash('sha256', 'sid-1'), UserSession::hashOf('sid-1'));
        self::assertSame(64, \strlen(UserSession::hashOf('anything')));
    }
}
