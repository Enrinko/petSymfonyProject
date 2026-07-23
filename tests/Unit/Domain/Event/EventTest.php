<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Event;

use App\Domain\Client\Client;
use App\Domain\Event\Event;
use App\Domain\Event\EventKind;
use App\Domain\Event\Exception\InvalidEventException;
use App\Domain\Repertoire\RepertoirePiece;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    public function testCreateNormalizesFields(): void
    {
        $event = Event::create('  Отчётный концерт ', EventKind::Concert, new \DateTimeImmutable('2026-12-20 18:00'), '  Актовый зал ', null);

        self::assertSame('Отчётный концерт', $event->getTitle());
        self::assertSame(EventKind::Concert, $event->getKind());
        self::assertSame('Актовый зал', $event->getVenue());
        self::assertNull($event->getDescription());
    }

    public function testBlankTitleIsRejected(): void
    {
        $this->expectException(InvalidEventException::class);

        Event::create('  ', EventKind::Concert, new \DateTimeImmutable(), null, null);
    }

    public function testKindLabels(): void
    {
        self::assertSame('Концерт', EventKind::Concert->label());
        self::assertSame('Экзамен', EventKind::Exam->label());
        self::assertSame('Конкурс', EventKind::Contest->label());
    }

    public function testAddProgramItemWithPieceAndWithCustomTitle(): void
    {
        $event = self::event();
        $client = self::client();
        $piece = RepertoirePiece::add($client, 'Этюд №12', 'Черни', new \DateTimeImmutable());

        $withPiece = $event->addProgramItem($client, $piece, null);
        $withText = $event->addProgramItem($client, null, 'Свободная импровизация');

        self::assertCount(2, $event->getProgram());
        self::assertSame('Этюд №12', $withPiece->displayTitle());
        self::assertSame('Свободная импровизация', $withText->displayTitle());
        self::assertSame(10, $withPiece->getSortOrder());
        self::assertSame(20, $withText->getSortOrder(), 'Шаг сортировки — 10');
    }

    public function testProgramItemRequiresPieceOrTitle(): void
    {
        $this->expectException(InvalidEventException::class);

        self::event()->addProgramItem(self::client(), null, '   ');
    }

    public function testPieceMustBelongToTheSameClient(): void
    {
        $event = self::event();
        $owner = User::register('t@example.com', 'hash');
        $anna = Client::create('Анна', $owner, new \DateTimeImmutable());
        $petr = Client::create('Пётр', $owner, new \DateTimeImmutable());
        $annasPiece = RepertoirePiece::add($anna, 'Этюд', 'Черни', new \DateTimeImmutable());

        $this->expectException(InvalidEventException::class);

        $event->addProgramItem($petr, $annasPiece, null);
    }

    public function testMoveItemSwapsWithNeighbour(): void
    {
        $event = self::event();
        $client = self::client();
        $first = $event->addProgramItem($client, null, 'Номер 1');
        $second = $event->addProgramItem($client, null, 'Номер 2');
        $third = $event->addProgramItem($client, null, 'Номер 3');

        $event->moveItem($second, up: true);

        self::assertSame(
            ['Номер 2', 'Номер 1', 'Номер 3'],
            array_map(static fn ($i) => $i->displayTitle(), $event->getProgram()),
        );

        $event->moveItem($first, up: false); // «Номер 1» вниз, в самый конец

        self::assertSame(
            ['Номер 2', 'Номер 3', 'Номер 1'],
            array_map(static fn ($i) => $i->displayTitle(), $event->getProgram()),
        );
    }

    public function testMoveAtEdgeIsNoop(): void
    {
        $event = self::event();
        $client = self::client();
        $only = $event->addProgramItem($client, null, 'Единственный');

        $event->moveItem($only, up: true);
        $event->moveItem($only, up: false);

        self::assertSame(['Единственный'], array_map(static fn ($i) => $i->displayTitle(), $event->getProgram()));
    }

    public function testRemoveItem(): void
    {
        $event = self::event();
        $client = self::client();
        $item = $event->addProgramItem($client, null, 'Номер');

        $event->removeItem($item);

        self::assertSame([], $event->getProgram());
    }

    private static function event(): Event
    {
        return Event::create('Концерт', EventKind::Concert, new \DateTimeImmutable('2026-12-20 18:00'), null, null);
    }

    private static function client(): Client
    {
        return Client::create('Анна', User::register('t@example.com', 'hash'), new \DateTimeImmutable());
    }
}
