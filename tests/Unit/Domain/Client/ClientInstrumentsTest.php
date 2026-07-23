<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Client;

use App\Domain\Client\Client;
use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentCategory;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class ClientInstrumentsTest extends TestCase
{
    public function testSyncInstrumentsReplacesWholeSet(): void
    {
        $client = self::client();
        $piano = Instrument::create('Фортепиано', InstrumentCategory::Keyboard, 0);
        $vocal = Instrument::create('Вокал', InstrumentCategory::Vocal, 0);

        $client->syncInstruments([$piano, $vocal]);
        self::assertSame(['Фортепиано', 'Вокал'], self::names($client));

        $guitar = Instrument::create('Гитара', InstrumentCategory::Strings, 0);
        $client->syncInstruments([$guitar]);
        self::assertSame(['Гитара'], self::names($client), 'Старый набор заменён');
    }

    public function testSyncInstrumentsDeduplicates(): void
    {
        $client = self::client();
        $piano = Instrument::create('Фортепиано', InstrumentCategory::Keyboard, 0);

        $client->syncInstruments([$piano, $piano]);

        self::assertCount(1, $client->getInstruments());
    }

    public function testNewClientHasNoInstruments(): void
    {
        self::assertSame([], self::client()->getInstruments());
    }

    /**
     * @return list<string>
     */
    private static function names(Client $client): array
    {
        return array_map(static fn (Instrument $i): string => $i->getName(), $client->getInstruments());
    }

    private static function client(): Client
    {
        return Client::create('Анна', User::register('t@example.com', 'hash'), new \DateTimeImmutable());
    }
}
