<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Client;

use App\Application\Client\ListClientsHandler;
use App\Application\Client\ListClientsQuery;
use App\Domain\Client\Client;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use PHPUnit\Framework\TestCase;

final class ListClientsHandlerTest extends TestCase
{
    public function testArchivedClientsAreHiddenByDefault(): void
    {
        $owner = User::register('t@example.com', 'hash');
        $active = Client::create('Активный', $owner, new \DateTimeImmutable());
        $archived = Client::create('Архивный', $owner, new \DateTimeImmutable());
        $archived->archive(new \DateTimeImmutable());

        $clients = new InMemoryClientRepository()->withClient(1, $active)->withClient(2, $archived);
        $handler = new ListClientsHandler($clients);

        $page = $handler(new ListClientsQuery(1, 20, '', false));

        self::assertCount(1, $page->data);
        self::assertSame('Активный', $page->data[0]->name);
        self::assertSame(1, $page->total);

        $withArchived = $handler(new ListClientsQuery(1, 20, '', true));
        self::assertCount(2, $withArchived->data);
    }

    public function testPageAndLimitAreClamped(): void
    {
        $handler = new ListClientsHandler(new InMemoryClientRepository());

        $page = $handler(new ListClientsQuery(-5, 100500, '', false));

        self::assertSame(1, $page->page);
        self::assertSame(100, $page->limit, 'limit ограничен сотней');
    }

    public function testTagFilterKeepsOnlyTaggedClients(): void
    {
        $owner = User::register('t@example.com', 'hash');
        $vocal = \App\Domain\Tag\Tag::create('вокал');

        $withTag = Client::create('С тегом', $owner, new \DateTimeImmutable());
        $withTag->syncTags([$vocal]);
        $withoutTag = Client::create('Без тега', $owner, new \DateTimeImmutable());

        $clients = new InMemoryClientRepository()->withClient(1, $withTag)->withClient(2, $withoutTag);
        $handler = new ListClientsHandler($clients);

        $page = $handler(new ListClientsQuery(1, 20, '', false, null, ['вокал']));

        self::assertCount(1, $page->data);
        self::assertSame('С тегом', $page->data[0]->name);
    }

    public function testOwnerScopeReturnsOnlyOwnClients(): void
    {
        $teacherA = User::register('a@example.com', 'hash');
        $teacherB = User::register('b@example.com', 'hash');

        $clients = new InMemoryClientRepository()
            ->withClient(1, Client::create('Ученик А', $teacherA, new \DateTimeImmutable()))
            ->withClient(2, Client::create('Ученик Б', $teacherB, new \DateTimeImmutable()));

        $handler = new ListClientsHandler($clients);

        $scoped = $handler(new ListClientsQuery(1, 20, '', false, $teacherA));

        self::assertCount(1, $scoped->data);
        self::assertSame('Ученик А', $scoped->data[0]->name);
        self::assertSame(1, $scoped->total);

        $unscoped = $handler(new ListClientsQuery(1, 20, '', false, null));
        self::assertCount(2, $unscoped->data, 'Без owner-скоупа (модератор/админ) видны все');
    }
}
