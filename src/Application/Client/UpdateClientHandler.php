<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Client\Exception\ClientNotFoundException;

use App\Application\Instrument\InstrumentResolver;
use App\Application\Search\SearchIndexQueueInterface;

final readonly class UpdateClientHandler
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private TagResolver $tagResolver,
        private InstrumentResolver $instrumentResolver,
        private SearchIndexQueueInterface $searchIndexQueue,
    ) {
    }

    /**
     * @throws ClientNotFoundException
     */
    public function __invoke(UpdateClientCommand $command): Client
    {
        $client = $this->clients->find($command->clientId)
            ?? throw new ClientNotFoundException(sprintf('Client #%d not found.', $command->clientId));

        $client->update(
            $command->name,
            new \DateTimeImmutable(),
            $command->email,
            $command->phone,
            $command->comment,
        );

        $client->syncTags($this->tagResolver->resolve($command->tags));
        $client->syncInstruments($this->instrumentResolver->resolve($command->instrumentIds));

        $this->clients->save($client);
        $this->searchIndexQueue->queueClient($command->clientId);

        return $client;
    }
}
