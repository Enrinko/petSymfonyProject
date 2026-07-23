<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Note;

use App\Domain\Client\Client;
use App\Domain\Note\Exception\InvalidNoteContentException;
use App\Domain\Note\Note;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class NoteTest extends TestCase
{
    public function testCreateTrimsContentAndBindsAuthor(): void
    {
        $author = self::user('teacher@example.com');
        $now = new \DateTimeImmutable('2026-07-22 10:00:00');

        $note = Note::create(self::client($author), $author, "  Разобрали гаммы.\nЗадал этюд №12.  ", $now);

        self::assertSame("Разобрали гаммы.\nЗадал этюд №12.", $note->getContent(), 'Переносы внутри сохраняются, края триммятся');
        self::assertSame($author, $note->getAuthor());
        self::assertSame($now, $note->getCreatedAt());
        self::assertNull($note->getUpdatedAt());
    }

    public function testBlankContentIsRejected(): void
    {
        $author = self::user('teacher@example.com');

        $this->expectException(InvalidNoteContentException::class);

        Note::create(self::client($author), $author, "   \n  ", new \DateTimeImmutable());
    }

    public function testUpdateContentTouchesUpdatedAt(): void
    {
        $author = self::user('teacher@example.com');
        $created = new \DateTimeImmutable('2026-07-22 10:00:00');
        $updated = new \DateTimeImmutable('2026-07-22 12:00:00');
        $note = Note::create(self::client($author), $author, 'Черновик', $created);

        $note->updateContent('Разобрали гаммы', $updated);

        self::assertSame('Разобрали гаммы', $note->getContent());
        self::assertSame($updated, $note->getUpdatedAt());
    }

    public function testAuthorCanManageWithin24Hours(): void
    {
        $author = self::user('teacher@example.com');
        $created = new \DateTimeImmutable('2026-07-22 10:00:00');
        $note = Note::create(self::client($author), $author, 'Запись', $created);

        self::assertTrue($note->isManageableBy($author, $created->modify('+23 hours 59 minutes')));
        self::assertFalse($note->isManageableBy($author, $created->modify('+24 hours')), 'Ровно через 24 часа окно закрыто');
    }

    public function testStrangerCannotManageEvenFreshNote(): void
    {
        $author = self::user('teacher@example.com');
        $created = new \DateTimeImmutable('2026-07-22 10:00:00');
        $note = Note::create(self::client($author), $author, 'Запись', $created);

        self::assertFalse($note->isManageableBy(self::user('other@example.com'), $created->modify('+1 minute')));
    }

    private static function user(string $email): User
    {
        return User::register($email, 'hash');
    }

    private static function client(User $owner): Client
    {
        return Client::create('Анна', $owner, new \DateTimeImmutable('2026-07-01 09:00:00'));
    }
}
