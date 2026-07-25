<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Search;

use App\Application\Search\NoteHitView;
use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class NoteHitViewTest extends TestCase
{
    public function testExternalFragmentFromMiddleGetsEllipsisOnBothSides(): void
    {
        $content = 'Начало длинной заметки. РАЗОБРАЛИ ЭТЮД Черни целиком. И ещё длинный хвост рассуждений.';
        $note = self::note($content);

        $view = NoteHitView::fromNoteWithSnippet($note, 'РАЗОБРАЛИ ЭТЮД Черни целиком.');

        self::assertSame('…РАЗОБРАЛИ ЭТЮД Черни целиком.…', $view->snippet);
    }

    public function testFragmentCoveringWholeContentHasNoEllipsis(): void
    {
        $note = self::note('короткая заметка');

        $view = NoteHitView::fromNoteWithSnippet($note, 'короткая заметка');

        self::assertSame('короткая заметка', $view->snippet);
    }

    public function testMultilineFragmentIsFlattenedToSingleLine(): void
    {
        $note = self::note("первая строка\nвторая строка\nтретья строка");

        $view = NoteHitView::fromNoteWithSnippet($note, "первая строка\nвторая строка\nтретья строка");

        self::assertSame('первая строка вторая строка третья строка', $view->snippet);
    }

    private static function note(string $content): Note
    {
        $owner = User::register('teacher@example.com', 'hash');
        $client = Client::create('Анна', $owner, new \DateTimeImmutable());

        return Note::create($client, $owner, $content, new \DateTimeImmutable());
    }
}
