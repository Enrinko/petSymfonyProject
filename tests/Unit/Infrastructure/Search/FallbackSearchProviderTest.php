<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Search;

use App\Application\Search\ClientHitView;
use App\Application\Search\SearchResults;
use App\Domain\Client\Client;
use App\Domain\User\User;
use App\Infrastructure\Search\FallbackSearchProvider;
use App\Tests\Fake\FakeSearchProvider;
use App\Tests\Fake\SpyLogger;
use PHPUnit\Framework\TestCase;

final class FallbackSearchProviderTest extends TestCase
{
    public function testHealthyPrimaryAnswersWithoutFallback(): void
    {
        $primaryResults = new SearchResults([self::hit()], [], []);
        $primary = new FakeSearchProvider($primaryResults);
        $fallback = new FakeSearchProvider();
        $logger = new SpyLogger();

        $results = new FallbackSearchProvider($primary, $fallback, $logger)->search('анна', null);

        self::assertSame($primaryResults, $results);
        self::assertSame([], $fallback->calls);
        self::assertSame([], $logger->records);
    }

    public function testBrokenPrimaryFallsBackWithWarning(): void
    {
        $fallbackResults = new SearchResults([self::hit()], [], []);
        $primary = new FakeSearchProvider()->willThrow(new \RuntimeException('no node available'));
        $fallback = new FakeSearchProvider($fallbackResults);
        $logger = new SpyLogger();
        $owner = User::register('teacher@example.com', 'hash');

        $results = new FallbackSearchProvider($primary, $fallback, $logger)->search('анна', $owner);

        self::assertSame($fallbackResults, $results);
        self::assertCount(1, $fallback->calls);
        self::assertSame($owner, $fallback->calls[0]['owner']);
        self::assertSame('warning', $logger->lastLevel());
        self::assertStringContainsString('fall', (string) $logger->lastMessage());
    }

    private static function hit(): ClientHitView
    {
        $owner = User::register('teacher@example.com', 'hash');

        return ClientHitView::fromClient(Client::create('Анна', $owner, new \DateTimeImmutable()));
    }
}
