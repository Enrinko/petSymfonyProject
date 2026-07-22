<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Client;

use App\Application\Client\CreateClientCommand;
use App\Application\Client\CreateClientHandler;
use App\Domain\User\User;
use App\Tests\Fake\InMemoryClientRepository;
use PHPUnit\Framework\TestCase;

final class CreateClientHandlerTest extends TestCase
{
    public function testCreatesClientOwnedByActor(): void
    {
        $clients = new InMemoryClientRepository();
        $owner = User::register('teacher@example.com', 'hash');
        $handler = new CreateClientHandler($clients);

        $client = $handler(new CreateClientCommand('  Пётр Клавишев ', 'petr@example.com', null, 'фортепиано'), $owner);

        self::assertSame('Пётр Клавишев', $client->getName());
        self::assertSame($owner, $client->getOwner());
        self::assertSame('фортепиано', $client->getComment());
        self::assertFalse($client->isArchived());
        self::assertCount(1, $clients->saved);
    }

    public function testEmptyOptionalFieldsBecomeNull(): void
    {
        $handler = new CreateClientHandler(new InMemoryClientRepository());

        $client = $handler(
            new CreateClientCommand('Анна', '', '  ', ''),
            User::register('teacher@example.com', 'hash'),
        );

        self::assertNull($client->getEmail());
        self::assertNull($client->getPhone());
        self::assertNull($client->getComment());
    }
}
