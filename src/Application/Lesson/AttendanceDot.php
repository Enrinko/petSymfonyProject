<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Lesson\Lesson;

/**
 * Точка истории посещаемости в карточке ученика.
 */
final readonly class AttendanceDot
{
    private function __construct(
        public int $lessonId,
        public string $attendance,
        public string $label,
        public string $startsAt,
    ) {
    }

    public static function fromLesson(Lesson $lesson): self
    {
        $attendance = $lesson->getAttendance();
        \assert($attendance !== null, 'Точки строятся только по закрытым занятиям');

        return new self(
            (int) $lesson->getId(),
            $attendance->value,
            $attendance->label(),
            $lesson->getStartsAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
