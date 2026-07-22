<?php

declare(strict_types=1);

namespace App\Domain\Lesson;

enum LessonStatus: string
{
    case Planned = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Запланировано',
            self::Completed => 'Проведено',
            self::Cancelled => 'Отменено',
        };
    }
}
