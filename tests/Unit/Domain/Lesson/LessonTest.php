<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Lesson;

use App\Domain\Client\Client;
use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentCategory;
use App\Domain\Lesson\Exception\InvalidLessonException;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonStatus;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class LessonTest extends TestCase
{
    public function testScheduledLessonHasEndTimeAndPlannedStatus(): void
    {
        $lesson = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 45);

        self::assertSame(LessonStatus::Planned, $lesson->getStatus());
        self::assertEquals(new \DateTimeImmutable('2026-07-20 15:45'), $lesson->getEndsAt());
        self::assertSame(45, $lesson->getDurationMinutes());
    }

    public function testZeroDurationIsRejected(): void
    {
        $this->expectException(InvalidLessonException::class);

        self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 0);
    }

    public function testOverlapsWhenTimesIntersectSameTeacher(): void
    {
        $a = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 60);
        $b = self::lesson(new \DateTimeImmutable('2026-07-20 15:30'), 60);

        self::assertTrue($a->overlaps($b));
        self::assertTrue($b->overlaps($a));
    }

    public function testBackToBackLessonsDoNotOverlap(): void
    {
        $a = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 60);
        $b = self::lesson(new \DateTimeImmutable('2026-07-20 16:00'), 60);

        self::assertFalse($a->overlaps($b), 'Стык впритык (16:00 после 15:00–16:00) — не пересечение');
    }

    public function testCompleteRequiresLessonToHaveStarted(): void
    {
        $lesson = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 45);

        $lesson->complete(new \DateTimeImmutable('2026-07-20 15:50'));
        self::assertSame(LessonStatus::Completed, $lesson->getStatus());
    }

    public function testCompleteBeforeStartIsRejected(): void
    {
        $lesson = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 45);

        $this->expectException(InvalidLessonException::class);

        $lesson->complete(new \DateTimeImmutable('2026-07-20 14:59'));
    }

    public function testCancelRequiresReason(): void
    {
        $lesson = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 45);

        $this->expectException(InvalidLessonException::class);

        $lesson->cancel('   ');
    }

    public function testCancelStoresReasonAndStatus(): void
    {
        $lesson = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 45);

        $lesson->cancel('  Ученик заболел  ');

        self::assertSame(LessonStatus::Cancelled, $lesson->getStatus());
        self::assertSame('Ученик заболел', $lesson->getCancelReason());
    }

    public function testCompletedLessonCannotBeRescheduled(): void
    {
        $lesson = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 45);
        $lesson->complete(new \DateTimeImmutable('2026-07-20 15:50'));

        $this->expectException(InvalidLessonException::class);

        $lesson->reschedule(new \DateTimeImmutable('2026-07-21 15:00'), 45);
    }

    public function testRescheduleMovesPlannedLesson(): void
    {
        $lesson = self::lesson(new \DateTimeImmutable('2026-07-20 15:00'), 45);

        $lesson->reschedule(new \DateTimeImmutable('2026-07-21 16:00'), 60);

        self::assertEquals(new \DateTimeImmutable('2026-07-21 16:00'), $lesson->getStartsAt());
        self::assertSame(60, $lesson->getDurationMinutes());
    }

    private static function lesson(\DateTimeImmutable $startsAt, int $duration): Lesson
    {
        $teacher = User::register('teacher@example.com', 'hash');
        $client = Client::create('Анна', $teacher, new \DateTimeImmutable());
        $instrument = Instrument::create('Фортепиано', InstrumentCategory::Keyboard, 0);

        return Lesson::schedule($teacher, $client, $instrument, $startsAt, $duration);
    }
}
