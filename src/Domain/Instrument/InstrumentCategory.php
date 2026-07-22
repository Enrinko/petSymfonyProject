<?php

declare(strict_types=1);

namespace App\Domain\Instrument;

enum InstrumentCategory: string
{
    case Keyboard = 'keyboard';
    case Strings = 'strings';
    case Winds = 'winds';
    case Percussion = 'percussion';
    case Vocal = 'vocal';

    public function label(): string
    {
        return match ($this) {
            self::Keyboard => 'Клавишные',
            self::Strings => 'Струнные',
            self::Winds => 'Духовые',
            self::Percussion => 'Ударные',
            self::Vocal => 'Вокал',
        };
    }

    /**
     * Значения для Assert\Choice — именно строки, а не enum-объекты из cases().
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
