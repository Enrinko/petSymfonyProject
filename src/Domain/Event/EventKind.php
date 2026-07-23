<?php

declare(strict_types=1);

namespace App\Domain\Event;

enum EventKind: string
{
    case Concert = 'concert';
    case Exam = 'exam';
    case Contest = 'contest';

    public function label(): string
    {
        return match ($this) {
            self::Concert => 'Концерт',
            self::Exam => 'Экзамен',
            self::Contest => 'Конкурс',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
