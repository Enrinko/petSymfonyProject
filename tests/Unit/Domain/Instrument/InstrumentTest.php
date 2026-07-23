<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Instrument;

use App\Domain\Instrument\Exception\InvalidInstrumentNameException;
use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentCategory;
use PHPUnit\Framework\TestCase;

final class InstrumentTest extends TestCase
{
    public function testCreateTrimsNameAndKeepsCategory(): void
    {
        $instrument = Instrument::create('  Фортепиано ', InstrumentCategory::Keyboard, 10);

        self::assertSame('Фортепиано', $instrument->getName());
        self::assertSame(InstrumentCategory::Keyboard, $instrument->getCategory());
        self::assertSame(10, $instrument->getSortOrder());
    }

    public function testBlankNameIsRejected(): void
    {
        $this->expectException(InvalidInstrumentNameException::class);

        Instrument::create('   ', InstrumentCategory::Vocal, 0);
    }

    public function testRenameChangesNameAndCategory(): void
    {
        $instrument = Instrument::create('Скрипка', InstrumentCategory::Strings, 5);

        $instrument->rename('Альт', InstrumentCategory::Strings);

        self::assertSame('Альт', $instrument->getName());
    }

    public function testCategoryEnumHasHumanLabels(): void
    {
        self::assertSame('Клавишные', InstrumentCategory::Keyboard->label());
        self::assertSame('Струнные', InstrumentCategory::Strings->label());
        self::assertSame('Духовые', InstrumentCategory::Winds->label());
        self::assertSame('Ударные', InstrumentCategory::Percussion->label());
        self::assertSame('Вокал', InstrumentCategory::Vocal->label());
    }
}
