<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Dashboard;

use App\Application\Dashboard\DashboardHandler;
use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use App\Tests\Fake\InMemoryNoteRepository;
use PHPUnit\Framework\TestCase;

final class DashboardHandlerTest extends TestCase
{
    public function testAggregatesTotalsNewThisMonthAndRecentNotes(): void
    {
        $owner = self::owner();

        $fresh = Client::create('Новый Ученик', $owner, new \DateTimeImmutable());
        $old = Client::create('Старый Ученик', $owner, new \DateTimeImmutable('2000-01-01'));
        $clients = new InMemoryClientRepository()->withClient(1, $fresh)->withClient(2, $old);

        $note = Note::create($fresh, $owner, "  Разобрали гаммы\nи этюд  ", new \DateTimeImmutable());
        $notes = new InMemoryNoteRepository()->withNote(1, $note);

        $view = new DashboardHandler($clients, $notes)($owner);

        self::assertSame(2, $view->clientsTotal);
        self::assertSame(1, $view->clientsNewThisMonth, 'Только созданный в этом месяце');
        self::assertCount(1, $view->recentNotes);
        self::assertSame('Новый Ученик', $view->recentNotes[0]->clientName);
        self::assertSame('Разобрали гаммы и этюд', $view->recentNotes[0]->preview, 'Переносы схлопнуты');
    }

    public function testEmptyDashboardForNewUser(): void
    {
        $view = new DashboardHandler(new InMemoryClientRepository(), new InMemoryNoteRepository())(self::owner());

        self::assertSame(0, $view->clientsTotal);
        self::assertSame(0, $view->clientsNewThisMonth);
        self::assertSame([], $view->recentNotes);
    }

    public function testOwnerScopeAppliesToTotalsAndNotes(): void
    {
        $mine = self::owner();
        $foreign = User::register('other@example.com', 'hash');

        $myClient = Client::create('Мой', $mine, new \DateTimeImmutable());
        $foreignClient = Client::create('Чужой', $foreign, new \DateTimeImmutable());
        $clients = new InMemoryClientRepository()->withClient(1, $myClient)->withClient(2, $foreignClient);

        $notes = new InMemoryNoteRepository()
            ->withNote(1, Note::create($myClient, $mine, 'моя заметка', new \DateTimeImmutable()))
            ->withNote(2, Note::create($foreignClient, $foreign, 'чужая заметка', new \DateTimeImmutable()));

        $view = new DashboardHandler($clients, $notes)($mine);

        self::assertSame(1, $view->clientsTotal);
        self::assertCount(1, $view->recentNotes);
        self::assertSame('моя заметка', $view->recentNotes[0]->preview);
    }

    private static function owner(): User
    {
        return User::register('teacher@example.com', 'hash');
    }
}
