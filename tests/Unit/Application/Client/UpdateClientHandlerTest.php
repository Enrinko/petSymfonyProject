<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Client;

use App\Application\Client\TagResolver;
use App\Application\Client\UpdateClientCommand;
use App\Application\Client\UpdateClientHandler;
use App\Domain\Client\Client;
use App\Domain\Client\Exception\ClientNotFoundException;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use App\Tests\Fake\InMemoryTagRepository;
use PHPUnit\Framework\TestCase;

final class UpdateClientHandlerTest extends TestCase
{
    public function testUpdatesExistingClient(): void
    {
        $client = Client::create('Анна', User::register('t@example.com', 'hash'), new \DateTimeImmutable());
        $clients = new InMemoryClientRepository()->withClient(7, $client);
        $handler = new UpdateClientHandler($clients, self::tagResolver(), self::instrumentResolver());

        $updated = $handler(new UpdateClientCommand(7, 'Анна Скрипкина', 'anna@example.com', '+79000000000', null));

        self::assertSame('Анна Скрипкина', $updated->getName());
        self::assertSame('anna@example.com', $updated->getEmail());
        self::assertNotNull($updated->getUpdatedAt());
        self::assertCount(1, $clients->saved);
    }

    public function testUpdateReplacesTags(): void
    {
        $client = Client::create('Анна', User::register('t@example.com', 'hash'), new \DateTimeImmutable());
        $client->syncTags([\App\Domain\Tag\Tag::create('старый')]);
        $clients = new InMemoryClientRepository()->withClient(7, $client);
        $handler = new UpdateClientHandler($clients, self::tagResolver(), self::instrumentResolver());

        $updated = $handler(new UpdateClientCommand(7, 'Анна', null, null, null, ['Новый']));

        self::assertSame(
            ['новый'],
            array_map(static fn ($tag) => $tag->getName(), $updated->getTags()),
        );
    }

    public function testUnknownClientIsRejected(): void
    {
        $handler = new UpdateClientHandler(new InMemoryClientRepository(), self::tagResolver(), self::instrumentResolver());

        $this->expectException(ClientNotFoundException::class);

        $handler(new UpdateClientCommand(404, 'Кто-то', null, null, null));
    }

    private static function instrumentResolver(): \App\Application\Instrument\InstrumentResolver
    {
        return new \App\Application\Instrument\InstrumentResolver(new \App\Tests\Fake\InMemoryInstrumentRepository());
    }

    private static function tagResolver(): TagResolver
    {
        return new TagResolver(new InMemoryTagRepository());
    }
}
