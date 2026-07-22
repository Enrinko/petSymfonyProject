<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Repertoire;

use App\Domain\Client\Client;
use App\Domain\Repertoire\Exception\InvalidPieceException;
use App\Domain\Repertoire\PieceStatus;
use App\Domain\Repertoire\RepertoirePiece;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class RepertoirePieceTest extends TestCase
{
    public function testNewPieceStartsAsLearningWithTrimmedFields(): void
    {
        $piece = self::piece('  Этюд №12 ', '  Черни ');

        self::assertSame('Этюд №12', $piece->getTitle());
        self::assertSame('Черни', $piece->getComposer());
        self::assertSame(PieceStatus::Learning, $piece->getStatus());
        self::assertNull($piece->getNote());
    }

    public function testBlankTitleIsRejected(): void
    {
        $this->expectException(InvalidPieceException::class);

        self::piece('   ', 'Черни');
    }

    public function testComposerIsOptional(): void
    {
        self::assertNull(self::piece('Импровизация', null)->getComposer());
        self::assertNull(self::piece('Импровизация', '  ')->getComposer());
    }

    public function testAdvanceWalksForwardThroughAllStatuses(): void
    {
        $piece = self::piece('Этюд', 'Черни');

        $piece->advance();
        self::assertSame(PieceStatus::Memorizing, $piece->getStatus());

        $piece->advance();
        self::assertSame(PieceStatus::Ready, $piece->getStatus());

        $piece->advance();
        self::assertSame(PieceStatus::InRepertoire, $piece->getStatus());
    }

    public function testCannotAdvanceBeyondFinalStatus(): void
    {
        $piece = self::piece('Этюд', 'Черни');
        $piece->advance();
        $piece->advance();
        $piece->advance();

        $this->expectException(InvalidPieceException::class);

        $piece->advance();
    }

    public function testStepBackReturnsOneStatus(): void
    {
        $piece = self::piece('Этюд', 'Черни');
        $piece->advance(); // Memorizing

        $piece->stepBack();

        self::assertSame(PieceStatus::Learning, $piece->getStatus());
    }

    public function testCannotStepBackFromInitialStatus(): void
    {
        $this->expectException(InvalidPieceException::class);

        self::piece('Этюд', 'Черни')->stepBack();
    }

    public function testStatusLabelsAreHuman(): void
    {
        self::assertSame('Разбираем', PieceStatus::Learning->label());
        self::assertSame('Учим наизусть', PieceStatus::Memorizing->label());
        self::assertSame('Готово к выступлению', PieceStatus::Ready->label());
        self::assertSame('В репертуаре', PieceStatus::InRepertoire->label());
    }

    public function testUpdateNoteTrimsAndNullsEmpty(): void
    {
        $piece = self::piece('Этюд', 'Черни');

        $piece->updateNote('  темп 60, без педали  ');
        self::assertSame('темп 60, без педали', $piece->getNote());

        $piece->updateNote('   ');
        self::assertNull($piece->getNote());
    }

    private static function piece(string $title, ?string $composer): RepertoirePiece
    {
        $owner = User::register('t@example.com', 'hash');
        $client = Client::create('Анна', $owner, new \DateTimeImmutable('2026-01-01'));

        return RepertoirePiece::add($client, $title, $composer, new \DateTimeImmutable('2026-07-01'));
    }
}
