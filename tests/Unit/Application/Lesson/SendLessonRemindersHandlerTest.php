<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Lesson;

use App\Application\Lesson\SendLessonRemindersHandler;
use App\Domain\Client\Client;
use App\Domain\Lesson\Lesson;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryLessonRepository;
use App\Tests\Fake\SpyLessonReminderMailer;
use PHPUnit\Framework\TestCase;

final class SendLessonRemindersHandlerTest extends TestCase
{
    private const string NOW = '2026-07-23 12:00:00';

    public function testSendsRemindersOnlyForLessonsInsideWindow(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $client = self::client('anna@example.com');
        $lessons = new InMemoryLessonRepository();

        $inside = self::lesson($client, '2026-07-23 18:00');       // через 6 ч — в окне 24 ч
        $edge = self::lesson($client, '2026-07-24 11:59');         // за минуту до края — в окне
        $outside = self::lesson($client, '2026-07-25 13:00');      // за окном
        $started = self::lesson($client, '2026-07-23 11:00');      // уже началось — не слать

        $lessons->withLesson(1, $inside)->withLesson(2, $edge)->withLesson(3, $outside)->withLesson(4, $started);

        $mailer = new SpyLessonReminderMailer();
        $sent = new SendLessonRemindersHandler($lessons, $mailer, 24)($now);

        self::assertSame(2, $sent);
        self::assertCount(2, $mailer->sent);
        self::assertNotNull($inside->getReminderSentAt());
        self::assertNotNull($edge->getReminderSentAt());
        self::assertNull($outside->getReminderSentAt());
        self::assertNull($started->getReminderSentAt());
    }

    public function testSecondRunSendsNothing(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $client = self::client('anna@example.com');
        $lessons = new InMemoryLessonRepository()->withLesson(1, self::lesson($client, '2026-07-23 18:00'));
        $mailer = new SpyLessonReminderMailer();
        $handler = new SendLessonRemindersHandler($lessons, $mailer, 24);

        $handler($now);
        $second = $handler($now->modify('+5 minutes'));

        self::assertSame(0, $second, 'Повторный прогон идемпотентен');
        self::assertCount(1, $mailer->sent);
    }

    public function testClientWithoutEmailIsSkippedAndNotMarked(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $client = self::client(null);
        $lesson = self::lesson($client, '2026-07-23 18:00');
        $lessons = new InMemoryLessonRepository()->withLesson(1, $lesson);
        $mailer = new SpyLessonReminderMailer();

        $sent = new SendLessonRemindersHandler($lessons, $mailer, 24)($now);

        self::assertSame(0, $sent);
        self::assertCount(0, $mailer->sent);
        self::assertNull($lesson->getReminderSentAt(), 'Без email не помечаем: адрес могут добавить позже');
    }

    public function testCancelledLessonGetsNoReminder(): void
    {
        $now = new \DateTimeImmutable(self::NOW);
        $client = self::client('anna@example.com');
        $lesson = self::lesson($client, '2026-07-23 18:00');
        $lesson->cancel('Перенос');
        $lessons = new InMemoryLessonRepository()->withLesson(1, $lesson);
        $mailer = new SpyLessonReminderMailer();

        $sent = new SendLessonRemindersHandler($lessons, $mailer, 24)($now);

        self::assertSame(0, $sent);
    }

    private static function client(?string $email): Client
    {
        return Client::create('Анна', User::register('teacher@example.com', 'hash'), new \DateTimeImmutable('2026-01-01'), $email);
    }

    private static function lesson(Client $client, string $startsAt): Lesson
    {
        return Lesson::schedule($client->getOwner(), $client, null, new \DateTimeImmutable($startsAt), 45);
    }
}
