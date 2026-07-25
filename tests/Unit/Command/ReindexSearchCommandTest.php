<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ReindexSearchCommand;
use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use App\Tests\Fake\InMemoryNoteRepository;
use App\Tests\Fake\SpySearchIndex;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ReindexSearchCommandTest extends TestCase
{
    public function testRecreatesIndicesAndReindexesEverythingIncludingArchived(): void
    {
        $owner = User::register('teacher@example.com', 'hash');

        $active = Client::create('Анна Скрипкина', $owner, new \DateTimeImmutable());
        $archived = Client::create('Ушедшая Ученица', $owner, new \DateTimeImmutable());
        $archived->archive(new \DateTimeImmutable());

        $clients = new InMemoryClientRepository()
            ->withClient(1, $active)
            ->withClient(2, $archived);

        $notes = new InMemoryNoteRepository()
            ->withNote(1, Note::create($active, $owner, 'разобрали гаммы', new \DateTimeImmutable()))
            ->withNote(2, Note::create($active, $owner, 'выучили этюд', new \DateTimeImmutable()));

        $index = new SpySearchIndex();

        $tester = new CommandTester(new ReindexSearchCommand($clients, $notes, $index));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertSame(1, $index->recreated, 'Полная переиндексация всегда пересоздаёт индексы');

        $indexedClients = array_merge(...$index->clientBatches);
        self::assertCount(2, $indexedClients, 'Архивные клиенты тоже в индексе — палитра ищет по архиву');

        $indexedNotes = array_merge(...$index->noteBatches);
        self::assertCount(2, $indexedNotes);

        $display = $tester->getDisplay();
        self::assertStringContainsString('2', $display);
    }

    public function testEmptyDatabaseStillRecreatesIndices(): void
    {
        $index = new SpySearchIndex();

        $tester = new CommandTester(new ReindexSearchCommand(
            new InMemoryClientRepository(),
            new InMemoryNoteRepository(),
            $index,
        ));

        self::assertSame(0, $tester->execute([]));
        self::assertSame(1, $index->recreated);
        self::assertSame([], $index->clientBatches, 'Пустых bulk-запросов не делаем');
    }
}
