<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Instrument;

use App\Application\Instrument\CreateInstrumentCommand;
use App\Application\Instrument\CreateInstrumentHandler;
use App\Application\Instrument\InstrumentResolver;
use App\Application\Instrument\UpdateInstrumentCommand;
use App\Application\Instrument\UpdateInstrumentHandler;
use App\Domain\Instrument\Exception\InstrumentNameTakenException;
use App\Domain\Instrument\Exception\InstrumentNotFoundException;
use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentCategory;
use App\Tests\Fake\InMemoryInstrumentRepository;
use PHPUnit\Framework\TestCase;

final class InstrumentHandlersTest extends TestCase
{
    public function testUpdateRenamesAndReorders(): void
    {
        $instruments = new InMemoryInstrumentRepository()
            ->withInstrument(1, Instrument::create('Скрипка', InstrumentCategory::Strings, 10));
        $handler = new UpdateInstrumentHandler($instruments);

        $updated = $handler(new UpdateInstrumentCommand(1, 'Альт', 'strings', 15));

        self::assertSame('Альт', $updated->getName());
        self::assertSame(15, $updated->getSortOrder());
        self::assertCount(1, $instruments->saved);
    }

    public function testUpdateKeepingOwnNameIsAllowed(): void
    {
        $instruments = new InMemoryInstrumentRepository()
            ->withInstrument(1, Instrument::create('Скрипка', InstrumentCategory::Strings, 10));
        $handler = new UpdateInstrumentHandler($instruments);

        $updated = $handler(new UpdateInstrumentCommand(1, 'скрипка', 'strings', 20));

        self::assertSame('скрипка', $updated->getName());
    }

    public function testUpdateToExistingOtherNameIsRejected(): void
    {
        $instruments = new InMemoryInstrumentRepository()
            ->withInstrument(1, Instrument::create('Скрипка', InstrumentCategory::Strings, 10))
            ->withInstrument(2, Instrument::create('Гитара', InstrumentCategory::Strings, 20));
        $handler = new UpdateInstrumentHandler($instruments);

        $this->expectException(InstrumentNameTakenException::class);

        $handler(new UpdateInstrumentCommand(2, 'скрипка', 'strings', 20));
    }

    public function testUpdateUnknownInstrumentIsRejected(): void
    {
        $handler = new UpdateInstrumentHandler(new InMemoryInstrumentRepository());

        $this->expectException(InstrumentNotFoundException::class);

        $handler(new UpdateInstrumentCommand(404, 'Что-то', 'vocal', 0));
    }

    public function testCreateInstrumentSaves(): void
    {
        $instruments = new InMemoryInstrumentRepository();
        $handler = new CreateInstrumentHandler($instruments);

        $created = $handler(new CreateInstrumentCommand(' Саксофон ', 'winds', 30));

        self::assertSame('Саксофон', $created->getName());
        self::assertSame(InstrumentCategory::Winds, $created->getCategory());
        self::assertCount(1, $instruments->saved);
    }

    public function testDuplicateNameIsRejected(): void
    {
        $instruments = new InMemoryInstrumentRepository()
            ->withInstrument(1, Instrument::create('Скрипка', InstrumentCategory::Strings, 0));
        $handler = new CreateInstrumentHandler($instruments);

        $this->expectException(InstrumentNameTakenException::class);

        $handler(new CreateInstrumentCommand('скрипка', 'strings', 0));
    }

    public function testResolverReturnsOnlyKnownInstrumentsInGivenOrder(): void
    {
        $piano = Instrument::create('Фортепиано', InstrumentCategory::Keyboard, 0);
        $vocal = Instrument::create('Вокал', InstrumentCategory::Vocal, 0);
        $instruments = new InMemoryInstrumentRepository()->withInstrument(1, $piano)->withInstrument(2, $vocal);

        // id 999 не существует и молча отбрасывается (справочник — источник истины)
        $resolved = new InstrumentResolver($instruments)->resolve([2, 999, 1]);

        self::assertCount(2, $resolved);
        self::assertSame(['Вокал', 'Фортепиано'], array_map(static fn ($i) => $i->getName(), $resolved));
    }

    public function testResolverEmptyInputGivesEmptyList(): void
    {
        self::assertSame([], new InstrumentResolver(new InMemoryInstrumentRepository())->resolve([]));
    }
}
