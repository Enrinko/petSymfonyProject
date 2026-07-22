<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;

final readonly class ListClientsHandler
{
    private const int MAX_LIMIT = 100;

    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    public function __invoke(ListClientsQuery $query): ClientPageView
    {
        $page = max(1, $query->page);
        $limit = min(max(1, $query->limit), self::MAX_LIMIT);
        $search = trim($query->search);

        $data = array_map(
            static fn (Client $client): ClientView => ClientView::fromClient($client),
            $this->clients->findPage($page, $limit, $search, $query->includeArchived, $query->owner),
        );

        return new ClientPageView(
            $data,
            $this->clients->countBySearch($search, $query->includeArchived, $query->owner),
            $page,
            $limit,
        );
    }
}
