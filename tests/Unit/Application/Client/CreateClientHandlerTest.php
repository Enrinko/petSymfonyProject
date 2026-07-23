<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Client;

use App\Application\Client\CreateClientCommand;
use App\Application\Client\CreateClientHandler;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use PHPUnit\Framework\TestCase;

final class CreateClientHandlerTest extends TestCase
{
    public function testCreatesClientOwnedByActor(): void
    {
        $clients = new InMemoryClientRepository();
        $owner = User::register('teacher@example.com', 'hash');
        $handler = new CreateClientHandler($clients, self::tagResolver(), self::instrumentResolver());

        $client = $handler(new CreateClientCommand('  Пётр Клавишев ', 'petr@example.com', null, 'фортепиано'), $owner);

        self::assertSame('Пётр Клавишев', $client->getName());
        self::assertSame($owner, $client->getOwner());
        self::assertSame('фортепиано', $client->getComment());
        self::assertFalse($client->isArchived());
        self::assertSame([], $client->getTags());
        self::assertCount(1, $clients->saved);
    }

    public function testEmptyOptionalFieldsBecomeNull(): void
    {
        $handler = new CreateClientHandler(new InMemoryClientRepository(), self::tagResolver(), self::instrumentResolver());

        $client = $handler(
            new CreateClientCommand('Анна', '', '  ', ''),
            User::register('teacher@example.com', 'hash'),
        );

        self::assertNull($client->getEmail());
        self::assertNull($client->getPhone());
        self::assertNull($client->getComment());
    }

    public function testTagsAreResolvedOnCreate(): void
    {
        $handler = new CreateClientHandler(new InMemoryClientRepository(), self::tagResolver(), self::instrumentResolver());

        $client = $handler(
            new CreateClientCommand('Анна', null, null, null, ['Вокал', 'онлайн']),
            User::register('teacher@example.com', 'hash'),
        );

        self::assertSame(
            ['вокал', 'онлайн'],
            array_map(static fn ($tag) => $tag->getName(), $client->getTags()),
        );
    }

    public function testInstrumentsAreResolvedOnCreate(): void
    {
        $instruments = new \App\Tests\Fake\InMemoryInstrumentRepository()
            ->withInstrument(1, \App\Domain\Instrument\Instrument::create('Фортепиано', \App\Domain\Instrument\InstrumentCategory::Keyboard, 0))
            ->withInstrument(2, \App\Domain\Instrument\Instrument::create('Вокал', \App\Domain\Instrument\InstrumentCategory::Vocal, 0));

        $handler = new CreateClientHandler(
            new InMemoryClientRepository(),
            self::tagResolver(),
            new \App\Application\Instrument\InstrumentResolver($instruments),
        );

        $client = $handler(
            new CreateClientCommand('Анна', null, null, null, [], [2, 1]),
            User::register('teacher@example.com', 'hash'),
        );

        self::assertSame(
            ['Вокал', 'Фортепиано'],
            array_map(static fn ($i) => $i->getName(), $client->getInstruments()),
        );
    }

    private static function tagResolver(): \App\Application\Client\TagResolver
    {
        return new \App\Application\Client\TagResolver(new \App\Tests\Fake\InMemoryTagRepository());
    }

    private static function instrumentResolver(): \App\Application\Instrument\InstrumentResolver
    {
        return new \App\Application\Instrument\InstrumentResolver(new \App\Tests\Fake\InMemoryInstrumentRepository());
    }
}
