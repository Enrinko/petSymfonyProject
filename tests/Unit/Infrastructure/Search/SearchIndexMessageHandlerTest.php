<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Search;

use App\Application\Search\Message\IndexClientMessage;
use App\Application\Search\Message\IndexNoteMessage;
use App\Application\Search\Message\RemoveNoteFromIndexMessage;
use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\User\User;
use App\Infrastructure\Search\SearchIndexMessageHandler;
use App\Tests\Fake\InMemoryClientRepository;
use App\Tests\Fake\InMemoryNoteRepository;
use App\Tests\Fake\SpySearchIndex;
use PHPUnit\Framework\TestCase;

final class SearchIndexMessageHandlerTest extends TestCase
{
    public function testIndexesExistingClientEnsuringIndices(): void
    {
        $owner = User::register('teacher@example.com', 'hash');
        $client = Client::create('Анна', $owner, new \DateTimeImmutable());
        $index = new SpySearchIndex();
        $handler = self::handler(new InMemoryClientRepository()->withClient(5, $client), new InMemoryNoteRepository(), $index);

        $handler->indexClient(new IndexClientMessage(5));

        self::assertSame(1, $index->ensured, 'Маппинги создаются до первой записи');
        self::assertSame([[$client]], $index->clientBatches);
    }

    public function testMissingEntityIsSilentlySkipped(): void
    {
        $index = new SpySearchIndex();
        $handler = self::handler(new InMemoryClientRepository(), new InMemoryNoteRepository(), $index);

        $handler->indexClient(new IndexClientMessage(404));
        $handler->indexNote(new IndexNoteMessage(404));

        self::assertSame([], $index->clientBatches, 'Сообщение пережило удаление сущности — не ошибка');
        self::assertSame([], $index->noteBatches);
    }

    public function testNoteIndexingAndRemoval(): void
    {
        $owner = User::register('teacher@example.com', 'hash');
        $client = Client::create('Анна', $owner, new \DateTimeImmutable());
        $note = Note::create($client, $owner, 'разобрали гаммы', new \DateTimeImmutable());
        $index = new SpySearchIndex();
        $handler = self::handler(new InMemoryClientRepository(), new InMemoryNoteRepository()->withNote(7, $note), $index);

        $handler->indexNote(new IndexNoteMessage(7));
        $handler->removeNote(new RemoveNoteFromIndexMessage(9));

        self::assertSame([[$note]], $index->noteBatches);
        self::assertSame([9], $index->removedNotes);
    }

    private static function handler(
        InMemoryClientRepository $clients,
        InMemoryNoteRepository $notes,
        SpySearchIndex $index,
    ): SearchIndexMessageHandler {
        return new SearchIndexMessageHandler($clients, $notes, $index);
    }
}
