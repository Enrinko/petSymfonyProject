<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Lesson;

use App\Domain\Client\Client;
use App\Domain\Lesson\Attendance;
use App\Domain\Lesson\Exception\InvalidLessonException;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonStatus;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class LessonAttendanceTest extends TestCase
{
    public function testPlannedLessonHasNoAttendance(): void
    {
        self::assertNull(self::plannedLesson('2026-07-20 15:00')->getAttendance());
    }

    public function testCompleteMarksAttended(): void
    {
        $lesson = self::plannedLesson('2026-07-20 15:00');

        $lesson->complete(new \DateTimeImmutable('2026-07-20 16:00'));

        self::assertSame(LessonStatus::Completed, $lesson->getStatus());
        self::assertSame(Attendance::Attended, $lesson->getAttendance());
    }

    public function testMarkMissedClosesLessonAsMissed(): void
    {
        $lesson = self::plannedLesson('2026-07-20 15:00');

        $lesson->markMissed(new \DateTimeImmutable('2026-07-20 16:00'));

        self::assertSame(LessonStatus::Completed, $lesson->getStatus());
        self::assertSame(Attendance::Missed, $lesson->getAttendance());
    }

    public function testCannotMarkMissedBeforeStart(): void
    {
        $lesson = self::plannedLesson('2026-07-20 15:00');

        $this->expectException(InvalidLessonException::class);

        $lesson->markMissed(new \DateTimeImmutable('2026-07-20 14:00'));
    }

    public function testCannotMarkMissedTwice(): void
    {
        $lesson = self::plannedLesson('2026-07-20 15:00');
        $lesson->markMissed(new \DateTimeImmutable('2026-07-20 16:00'));

        $this->expectException(InvalidLessonException::class);

        $lesson->markMissed(new \DateTimeImmutable('2026-07-20 17:00'));
    }

    public function testCancelByClientAndByTeacherAreDistinguished(): void
    {
        $byClient = self::plannedLesson('2026-07-20 15:00');
        $byClient->cancel('Заболел', cancelledByClient: true);
        self::assertSame(Attendance::CancelledByClient, $byClient->getAttendance());

        $byTeacher = self::plannedLesson('2026-07-21 15:00');
        $byTeacher->cancel('Командировка', cancelledByClient: false);
        self::assertSame(Attendance::CancelledByTeacher, $byTeacher->getAttendance());
    }

    public function testAttendanceLabels(): void
    {
        self::assertSame('Был', Attendance::Attended->label());
        self::assertSame('Пропустил', Attendance::Missed->label());
        self::assertSame('Отменил ученик', Attendance::CancelledByClient->label());
        self::assertSame('Отменил преподаватель', Attendance::CancelledByTeacher->label());
    }

    private static function plannedLesson(string $startsAt): Lesson
    {
        $teacher = User::register('teacher@example.com', 'hash');
        $client = Client::create('Анна', $teacher, new \DateTimeImmutable('2026-07-01'));

        return Lesson::schedule($teacher, $client, null, new \DateTimeImmutable($startsAt), 45);
    }
}
