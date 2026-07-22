<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\User\User;

final readonly class CreateClientHandler
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    public function __invoke(CreateClientCommand $command, User $owner): Client
    {
        $client = Client::create(
            $command->name,
            $owner,
            new \DateTimeImmutable(),
            $command->email,
            $command->phone,
            $command->comment,
        );

        $this->clients->save($client);

        return $client;
    }
}
