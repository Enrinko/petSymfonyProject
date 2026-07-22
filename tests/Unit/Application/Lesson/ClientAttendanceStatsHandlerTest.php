<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Lesson;

use App\Application\Lesson\ClientAttendanceStatsHandler;
use App\Domain\Client\Client;
use App\Domain\Lesson\Lesson;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryLessonRepository;
use PHPUnit\Framework\TestCase;

final class ClientAttendanceStatsHandlerTest extends TestCase
{
    public function testEmptyHistoryGivesZeroStatsAndNoAttention(): void
    {
        $client = self::client();
        $stats = new ClientAttendanceStatsHandler(new InMemoryLessonRepository())($client);

        self::assertSame(0, $stats->missed30);
        self::assertSame(0, $stats->held30);
        self::assertFalse($stats->needsAttention);
        self::assertSame([], $stats->recent);
    }

    public function testCountsMissedOfHeldSlotsFor30Days(): void
    {
        $client = self::client();
        $lessons = new InMemoryLessonRepository();

        // 3 состоявшихся слота за месяц: 2 был, 1 пропуск; отмена не считается слотом
        $lessons->withLesson(1, self::closed($client, '-2 days', attended: true));
        $lessons->withLesson(2, self::closed($client, '-5 days', attended: false));
        $lessons->withLesson(3, self::closed($client, '-8 days', attended: true));
        $lessons->withLesson(4, self::cancelled($client, '-3 days'));
        // старше 30 дней — не в счёт
        $lessons->withLesson(5, self::closed($client, '-40 days', attended: false));

        $stats = new ClientAttendanceStatsHandler($lessons)($client);

        self::assertSame(1, $stats->missed30);
        self::assertSame(3, $stats->held30);
        self::assertFalse($stats->needsAttention, '1 пропуск из 3 — ещё не тревога');
    }

    public function testTwoConsecutiveMissesRaiseAttention(): void
    {
        $client = self::client();
        $lessons = new InMemoryLessonRepository();

        $lessons->withLesson(1, self::closed($client, '-1 day', attended: false));
        $lessons->withLesson(2, self::closed($client, '-3 days', attended: false));
        $lessons->withLesson(3, self::closed($client, '-6 days', attended: true));

        $stats = new ClientAttendanceStatsHandler($lessons)($client);

        self::assertTrue($stats->needsAttention, '2 пропуска подряд');
    }

    public function testCancellationDoesNotBreakMissStreak(): void
    {
        $client = self::client();
        $lessons = new InMemoryLessonRepository();

        $lessons->withLesson(1, self::closed($client, '-1 day', attended: false));
        $lessons->withLesson(2, self::cancelled($client, '-2 days'));
        $lessons->withLesson(3, self::closed($client, '-4 days', attended: false));

        $stats = new ClientAttendanceStatsHandler($lessons)($client);

        self::assertTrue($stats->needsAttention, 'Отмена между пропусками не рвёт серию');
    }

    public function testThreeMissesInMonthRaiseAttentionEvenNotConsecutive(): void
    {
        $client = self::client();
        $lessons = new InMemoryLessonRepository();

        $lessons->withLesson(1, self::closed($client, '-2 days', attended: false));
        $lessons->withLesson(2, self::closed($client, '-6 days', attended: true));
        $lessons->withLesson(3, self::closed($client, '-10 days', attended: false));
        $lessons->withLesson(4, self::closed($client, '-15 days', attended: true));
        $lessons->withLesson(5, self::closed($client, '-20 days', attended: false));

        $stats = new ClientAttendanceStatsHandler($lessons)($client);

        self::assertTrue($stats->needsAttention, '3 пропуска за 30 дней');
    }

    public function testRecentDotsAreNewestFirstWithLabels(): void
    {
        $client = self::client();
        $lessons = new InMemoryLessonRepository();

        $lessons->withLesson(1, self::closed($client, '-1 day', attended: true));
        $lessons->withLesson(2, self::closed($client, '-2 days', attended: false));

        $stats = new ClientAttendanceStatsHandler($lessons)($client);

        self::assertCount(2, $stats->recent);
        self::assertSame('attended', $stats->recent[0]->attendance);
        self::assertSame('missed', $stats->recent[1]->attendance);
        self::assertSame('Был', $stats->recent[0]->label);
    }

    private static function client(): Client
    {
        return Client::create('Анна', User::register('t@example.com', 'hash'), new \DateTimeImmutable('2026-01-01'));
    }

    private static function closed(Client $client, string $when, bool $attended): Lesson
    {
        $start = new \DateTimeImmutable($when);
        $lesson = Lesson::schedule($client->getOwner(), $client, null, $start, 45);

        if ($attended) {
            $lesson->complete($start->modify('+1 hour'));
        } else {
            $lesson->markMissed($start->modify('+1 hour'));
        }

        return $lesson;
    }

    private static function cancelled(Client $client, string $when): Lesson
    {
        $lesson = Lesson::schedule($client->getOwner(), $client, null, new \DateTimeImmutable($when), 45);
        $lesson->cancel('Перенос');

        return $lesson;
    }
}
