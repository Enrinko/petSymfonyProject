<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Client;

use App\Application\Client\ArchiveClientHandler;
use App\Application\Client\RestoreClientHandler;
use App\Domain\Client\Client;
use App\Domain\Client\Exception\ClientNotFoundException;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use PHPUnit\Framework\TestCase;

final class ArchiveAndRestoreClientHandlerTest extends TestCase
{
    public function testArchiveThenRestore(): void
    {
        $client = Client::create('Анна', User::register('t@example.com', 'hash'), new \DateTimeImmutable());
        $clients = new InMemoryClientRepository()->withClient(1, $client);

        new ArchiveClientHandler($clients)(1);
        self::assertTrue($client->isArchived());

        new RestoreClientHandler($clients)(1);
        self::assertFalse($client->isArchived());

        self::assertCount(2, $clients->saved);
    }

    public function testArchiveUnknownClientIsRejected(): void
    {
        $this->expectException(ClientNotFoundException::class);

        new ArchiveClientHandler(new InMemoryClientRepository())(404);
    }

    public function testRestoreUnknownClientIsRejected(): void
    {
        $this->expectException(ClientNotFoundException::class);

        new RestoreClientHandler(new InMemoryClientRepository())(404);
    }
}
