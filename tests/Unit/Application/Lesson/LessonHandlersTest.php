<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Lesson;

use App\Application\Lesson\CancelLessonHandler;
use App\Application\Lesson\CompleteLessonHandler;
use App\Application\Lesson\RescheduleLessonCommand;
use App\Application\Lesson\RescheduleLessonHandler;
use App\Application\Lesson\ScheduleLessonCommand;
use App\Application\Lesson\ScheduleLessonHandler;
use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentCategory;
use App\Domain\Instrument\InstrumentRepositoryInterface;
use App\Domain\Lesson\Exception\LessonOverlapException;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonStatus;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use App\Tests\Fake\InMemoryInstrumentRepository;
use App\Tests\Fake\InMemoryLessonRepository;
use PHPUnit\Framework\TestCase;

final class LessonHandlersTest extends TestCase
{
    public function testScheduleCreatesLessonForOwnedClient(): void
    {
        $teacher = self::teacher();
        $client = Client::create('Анна', $teacher, new \DateTimeImmutable());
        $clients = new InMemoryClientRepository()->withClient(1, $client);
        $lessons = new InMemoryLessonRepository();

        $handler = new ScheduleLessonHandler($lessons, $clients, self::instruments());
        $lesson = $handler(new ScheduleLessonCommand(1, null, '2026-07-20T15:00:00', 45, null), $teacher);

        self::assertSame($client, $lesson->getClient());
        self::assertSame(45, $lesson->getDurationMinutes());
        self::assertCount(1, $lessons->saved);
    }

    public function testScheduleRejectsForeignClient(): void
    {
        $teacher = self::teacher();
        $foreignClient = Client::create('Чужой', User::register('other@example.com', 'hash'), new \DateTimeImmutable());
        $clients = new InMemoryClientRepository()->withClient(1, $foreignClient);

        $handler = new ScheduleLessonHandler(new InMemoryLessonRepository(), $clients, self::instruments());

        $this->expectException(\App\Domain\Client\Exception\ClientNotFoundException::class);

        $handler(new ScheduleLessonCommand(1, null, '2026-07-20T15:00:00', 45, null), $teacher);
    }

    public function testScheduleRejectsOverlapForSameTeacher(): void
    {
        $teacher = self::teacher();
        $client = Client::create('Анна', $teacher, new \DateTimeImmutable());
        $clients = new InMemoryClientRepository()->withClient(1, $client);

        $lessons = new InMemoryLessonRepository()->withLesson(
            100,
            Lesson::schedule($teacher, $client, null, new \DateTimeImmutable('2026-07-20 15:00'), 60),
        );

        $handler = new ScheduleLessonHandler($lessons, $clients, self::instruments());

        $this->expectException(LessonOverlapException::class);

        // 15:30 пересекает 15:00–16:00
        $handler(new ScheduleLessonCommand(1, null, '2026-07-20T15:30:00', 45, null), $teacher);
    }

    public function testRescheduleChecksOverlapExcludingItself(): void
    {
        $teacher = self::teacher();
        $client = Client::create('Анна', $teacher, new \DateTimeImmutable());
        $clients = new InMemoryClientRepository()->withClient(1, $client);

        $lesson = Lesson::schedule($teacher, $client, null, new \DateTimeImmutable('2026-07-20 15:00'), 60);
        $lessons = new InMemoryLessonRepository()->withLesson(100, $lesson);

        $handler = new RescheduleLessonHandler($lessons);

        // Двигаем в пределах себя же — не должно считаться пересечением с самим собой
        $handler(new RescheduleLessonCommand(100, '2026-07-20T15:15:00', 30), $teacher);

        self::assertEquals(new \DateTimeImmutable('2026-07-20 15:15'), $lesson->getStartsAt());
    }

    public function testCompleteMarksLessonDone(): void
    {
        $teacher = self::teacher();
        $client = Client::create('Анна', $teacher, new \DateTimeImmutable());
        $lesson = Lesson::schedule($teacher, $client, null, new \DateTimeImmutable('-1 hour'), 45);
        $lessons = new InMemoryLessonRepository()->withLesson(100, $lesson);

        new CompleteLessonHandler($lessons)(100, $teacher);

        self::assertSame(LessonStatus::Completed, $lesson->getStatus());
    }

    public function testCancelRequiresReason(): void
    {
        $teacher = self::teacher();
        $client = Client::create('Анна', $teacher, new \DateTimeImmutable());
        $lesson = Lesson::schedule($teacher, $client, null, new \DateTimeImmutable('2026-07-20 15:00'), 45);
        $lessons = new InMemoryLessonRepository()->withLesson(100, $lesson);

        new CancelLessonHandler($lessons)(100, 'Ученик заболел', $teacher);

        self::assertSame(LessonStatus::Cancelled, $lesson->getStatus());
        self::assertSame('Ученик заболел', $lesson->getCancelReason());
    }

    public function testActionsOnForeignTeacherLessonAreRejected(): void
    {
        $teacher = self::teacher();
        $other = User::register('other@example.com', 'hash');
        $client = Client::create('Анна', $other, new \DateTimeImmutable());
        $lesson = Lesson::schedule($other, $client, null, new \DateTimeImmutable('-1 hour'), 45);
        $lessons = new InMemoryLessonRepository()->withLesson(100, $lesson);

        $this->expectException(\App\Domain\Lesson\Exception\LessonNotFoundException::class);

        new CompleteLessonHandler($lessons)(100, $teacher);
    }

    private static function teacher(): User
    {
        return User::register('teacher@example.com', 'hash');
    }

    private static function instruments(): InstrumentRepositoryInterface
    {
        return new InMemoryInstrumentRepository()
            ->withInstrument(1, Instrument::create('Фортепиано', InstrumentCategory::Keyboard, 0));
    }
}
