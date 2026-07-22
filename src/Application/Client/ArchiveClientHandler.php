<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Client\Exception\ClientNotFoundException;

final readonly class ArchiveClientHandler
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    /**
     * @throws ClientNotFoundException
     */
    public function __invoke(int $clientId): Client
    {
        $client = $this->clients->find($clientId)
            ?? throw new ClientNotFoundException(sprintf('Client #%d not found.', $clientId));

        $client->archive(new \DateTimeImmutable());
        $this->clients->save($client);

        return $client;
    }
}
