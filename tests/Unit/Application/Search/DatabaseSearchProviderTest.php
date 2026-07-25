<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Search;

use App\Application\Search\DatabaseSearchProvider;
use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\Tag\Tag;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use App\Tests\Fake\InMemoryNoteRepository;
use App\Tests\Fake\InMemoryTagRepository;
use PHPUnit\Framework\TestCase;

final class DatabaseSearchProviderTest extends TestCase
{
    public function testFindsTagsWithUsageCount(): void
    {
        $tags = new InMemoryTagRepository()
            ->withUsage(Tag::create('вокал'), 3, null)
            ->withUsage(Tag::create('фортепиано'), 1, null);

        $results = self::provider(new InMemoryClientRepository(), new InMemoryNoteRepository(), $tags)->search('вок', null);

        self::assertCount(1, $results->tags);
        self::assertSame('вокал', $results->tags[0]->name);
        self::assertSame(3, $results->tags[0]->usageCount);
    }

    public function testTagsAreOwnerScoped(): void
    {
        $mine = self::owner();
        $foreign = User::register('other@example.com', 'hash');

        $tags = new InMemoryTagRepository()
            ->withUsage(Tag::create('вокал'), 2, $mine)
            ->withUsage(Tag::create('вокал-прод'), 5, $foreign);

        $results = self::provider(new InMemoryClientRepository(), new InMemoryNoteRepository(), $tags)->search('вокал', $mine);

        self::assertCount(1, $results->tags, 'Тег без видимых учеников не показывается');
        self::assertSame('вокал', $results->tags[0]->name);
    }

    public function testFindsClientsByNameCaseInsensitive(): void
    {
        $owner = self::owner();
        $clients = new InMemoryClientRepository()
            ->withClient(1, Client::create('Анна Скрипкина', $owner, new \DateTimeImmutable()))
            ->withClient(2, Client::create('Пётр Клавишев', $owner, new \DateTimeImmutable()));

        $results = self::provider($clients, new InMemoryNoteRepository())->search('скрипк', null);

        self::assertCount(1, $results->clients);
        self::assertSame('Анна Скрипкина', $results->clients[0]->name);
    }

    public function testArchivedClientIsFoundAndMarked(): void
    {
        $owner = self::owner();
        $archived = Client::create('Ушедшая Ученица', $owner, new \DateTimeImmutable());
        $archived->archive(new \DateTimeImmutable());

        $clients = new InMemoryClientRepository()->withClient(1, $archived);

        $results = self::provider($clients, new InMemoryNoteRepository())->search('ушедшая', null);

        self::assertCount(1, $results->clients, 'Палитра ищет и по архиву');
        self::assertTrue($results->clients[0]->archived);
    }

    public function testOwnerScopeAppliesToClientsAndNotes(): void
    {
        $mine = self::owner();
        $foreign = User::register('other@example.com', 'hash');

        $myClient = Client::create('Мой Ученик', $mine, new \DateTimeImmutable());
        $foreignClient = Client::create('Чужой Ученик', $foreign, new \DateTimeImmutable());

        $clients = new InMemoryClientRepository()->withClient(1, $myClient)->withClient(2, $foreignClient);
        $notes = new InMemoryNoteRepository()
            ->withNote(1, Note::create($myClient, $mine, 'ученик разобрал гаммы', new \DateTimeImmutable()))
            ->withNote(2, Note::create($foreignClient, $foreign, 'ученик пропустил урок', new \DateTimeImmutable()));

        $results = self::provider($clients, $notes)->search('ученик', $mine);

        self::assertCount(1, $results->clients);
        self::assertSame('Мой Ученик', $results->clients[0]->name);
        self::assertCount(1, $results->notes);
        self::assertStringContainsString('гаммы', $results->notes[0]->snippet);
    }

    public function testNoteSnippetIsCutAroundMatch(): void
    {
        $owner = self::owner();
        $client = Client::create('Анна', $owner, new \DateTimeImmutable());
        $long = str_repeat('до ре ми ', 20) . 'РАЗОБРАЛИ ЭТЮД Черни' . str_repeat(' фа соль ля', 20);

        $notes = new InMemoryNoteRepository()->withNote(1, Note::create($client, $owner, $long, new \DateTimeImmutable()));

        $results = self::provider(new InMemoryClientRepository()->withClient(1, $client), $notes)->search('этюд', null);

        $snippet = $results->notes[0]->snippet;
        self::assertStringContainsString('РАЗОБРАЛИ ЭТЮД Черни', $snippet, 'Вхождение в сниппете, регистр оригинала сохранён');
        self::assertStringStartsWith('…', $snippet);
        self::assertStringEndsWith('…', $snippet);
        self::assertLessThan(120, mb_strlen($snippet), 'Сниппет компактный (±40 символов вокруг вхождения)');
        self::assertSame('Анна', $results->notes[0]->clientName);
    }

    private static function provider(
        InMemoryClientRepository $clients,
        InMemoryNoteRepository $notes,
        ?InMemoryTagRepository $tags = null,
    ): DatabaseSearchProvider {
        return new DatabaseSearchProvider($clients, $notes, $tags ?? new InMemoryTagRepository());
    }

    private static function owner(): User
    {
        return User::register('teacher@example.com', 'hash');
    }
}
