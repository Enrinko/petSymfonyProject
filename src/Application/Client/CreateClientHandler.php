<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\User\User;

use App\Application\Instrument\InstrumentResolver;
use App\Application\Search\SearchIndexQueueInterface;

final readonly class CreateClientHandler
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private TagResolver $tagResolver,
        private InstrumentResolver $instrumentResolver,
        private SearchIndexQueueInterface $searchIndexQueue,
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

        $client->syncTags($this->tagResolver->resolve($command->tags));
        $client->syncInstruments($this->instrumentResolver->resolve($command->instrumentIds));

        $this->clients->save($client);
        $this->searchIndexQueue->queueClient((int) $client->getId());

        return $client;
    }
}
