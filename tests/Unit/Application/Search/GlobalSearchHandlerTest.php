<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Search;

use App\Application\Search\ClientHitView;
use App\Application\Search\GlobalSearchHandler;
use App\Application\Search\SearchResults;
use App\Domain\Client\Client;
use App\Domain\User\User;
use App\Tests\Fake\FakeSearchProvider;
use PHPUnit\Framework\TestCase;

final class GlobalSearchHandlerTest extends TestCase
{
    public function testShortQueryGivesEmptyGroupsWithoutTouchingProvider(): void
    {
        $provider = new FakeSearchProvider();

        $results = new GlobalSearchHandler($provider)(' а ', null);

        self::assertSame([], $results->clients);
        self::assertSame([], $results->tags);
        self::assertSame([], $results->notes);
        self::assertSame([], $provider->calls, 'Слишком короткий запрос не доходит до движка');
    }

    public function testTrimmedQueryIsDelegatedToProvider(): void
    {
        $owner = User::register('teacher@example.com', 'hash');
        $hit = ClientHitView::fromClient(Client::create('Анна', $owner, new \DateTimeImmutable()));
        $provider = new FakeSearchProvider(new SearchResults([$hit], [], []));

        $results = new GlobalSearchHandler($provider)('  анна  ', $owner);

        self::assertSame([['query' => 'анна', 'owner' => $owner]], $provider->calls);
        self::assertSame([$hit], $results->clients);
    }
}
