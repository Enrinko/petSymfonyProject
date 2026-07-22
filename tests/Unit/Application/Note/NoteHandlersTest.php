<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Note;

use App\Application\Note\AddNoteCommand;
use App\Application\Note\AddNoteHandler;
use App\Application\Note\ListClientNotesHandler;
use App\Application\Note\ListClientNotesQuery;
use App\Application\Note\RemoveNoteHandler;
use App\Application\Note\UpdateNoteCommand;
use App\Application\Note\UpdateNoteHandler;
use App\Domain\Client\Client;
use App\Domain\Note\Exception\NoteNotFoundException;
use App\Domain\Note\Note;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryNoteRepository;
use PHPUnit\Framework\TestCase;

final class NoteHandlersTest extends TestCase
{
    public function testAddNoteCreatesAndSaves(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $client = self::client($author);
        $notes = new InMemoryNoteRepository();

        $note = new AddNoteHandler($notes)(new AddNoteCommand('  Разобрали гаммы  '), $client, $author);

        self::assertSame('Разобрали гаммы', $note->getContent());
        self::assertSame($client, $note->getClient());
        self::assertSame($author, $note->getAuthor());
        self::assertCount(1, $notes->saved);
    }

    public function testListReturnsEnvelopeWithClampedLimit(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $client = self::client($author);
        $notes = new InMemoryNoteRepository()
            ->withNote(1, Note::create($client, $author, 'Первая', new \DateTimeImmutable()));

        $pageView = new ListClientNotesHandler($notes)(new ListClientNotesQuery($client, -3, 100500));

        self::assertCount(1, $pageView->notes);
        self::assertSame(1, $pageView->total);
        self::assertSame(1, $pageView->page);
        self::assertSame(100, $pageView->limit, 'limit ограничен сотней');
    }

    public function testUpdateChangesContent(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $note = Note::create(self::client($author), $author, 'Черновик', new \DateTimeImmutable());
        $notes = new InMemoryNoteRepository()->withNote(7, $note);

        $updated = new UpdateNoteHandler($notes)(new UpdateNoteCommand(7, 'Чистовик'));

        self::assertSame('Чистовик', $updated->getContent());
        self::assertNotNull($updated->getUpdatedAt());
        self::assertCount(1, $notes->saved);
    }

    public function testUpdateUnknownNoteIsRejected(): void
    {
        $this->expectException(NoteNotFoundException::class);

        new UpdateNoteHandler(new InMemoryNoteRepository())(new UpdateNoteCommand(404, 'Текст'));
    }

    public function testRemoveDeletesNote(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $note = Note::create(self::client($author), $author, 'Лишняя', new \DateTimeImmutable());
        $notes = new InMemoryNoteRepository()->withNote(7, $note);

        new RemoveNoteHandler($notes)(7);

        self::assertCount(1, $notes->removed);
        self::assertSame($note, $notes->removed[0]);
    }

    public function testRemoveUnknownNoteIsRejected(): void
    {
        $this->expectException(NoteNotFoundException::class);

        new RemoveNoteHandler(new InMemoryNoteRepository())(404);
    }

    private static function client(User $owner): Client
    {
        return Client::create('Анна', $owner, new \DateTimeImmutable('2026-07-01 09:00:00'));
    }
}
