<?php

declare(strict_types=1);

namespace App\Domain\Lesson;

/**
 * Исход занятия. Не отдельная сущность, а атрибут Lesson:
 * заполняется при завершении/отмене, для запланированных — null.
 */
enum Attendance: string
{
    case Attended = 'attended';
    case Missed = 'missed';
    case CancelledByClient = 'cancelled_by_client';
    case CancelledByTeacher = 'cancelled_by_teacher';

    public function label(): string
    {
        return match ($this) {
            self::Attended => 'Был',
            self::Missed => 'Пропустил',
            self::CancelledByClient => 'Отменил ученик',
            self::CancelledByTeacher => 'Отменил преподаватель',
        };
    }

    /** Пропуск с точки зрения «остывания» ученика. */
    public function countsAsMiss(): bool
    {
        return $this === self::Missed;
    }
}
