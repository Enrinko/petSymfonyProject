<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Client;

use App\Domain\Client\Client;
use App\Domain\Tag\Tag;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class ClientTagsTest extends TestCase
{
    public function testSyncTagsReplacesWholeSet(): void
    {
        $client = self::client();
        $vocal = Tag::create('вокал');
        $piano = Tag::create('фортепиано');

        $client->syncTags([$vocal, $piano]);
        self::assertSame(['вокал', 'фортепиано'], self::names($client));

        $online = Tag::create('онлайн');
        $client->syncTags([$piano, $online]);
        self::assertSame(['фортепиано', 'онлайн'], self::names($client), 'Старый набор полностью заменён');
    }

    public function testSyncTagsDeduplicates(): void
    {
        $client = self::client();
        $vocal = Tag::create('вокал');

        $client->syncTags([$vocal, $vocal]);

        self::assertCount(1, $client->getTags());
    }

    public function testNewClientHasNoTags(): void
    {
        self::assertSame([], self::client()->getTags());
    }

    /**
     * @return list<string>
     */
    private static function names(Client $client): array
    {
        return array_map(static fn (Tag $tag): string => $tag->getName(), $client->getTags());
    }

    private static function client(): Client
    {
        return Client::create('Анна', User::register('t@example.com', 'hash'), new \DateTimeImmutable());
    }
}
