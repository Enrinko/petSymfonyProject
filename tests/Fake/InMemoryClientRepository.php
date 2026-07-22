<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\User\User;

final class InMemoryClientRepository implements ClientRepositoryInterface
{
    /**
     * @var array<int, Client>
     */
    private array $byId = [];

    /**
     * @var list<Client>
     */
    public array $saved = [];

    public function withClient(int $id, Client $client): self
    {
        $this->byId[$id] = $client;

        return $this;
    }

    public function find(int $id): ?Client
    {
        return $this->byId[$id] ?? null;
    }

    public function findPage(int $page, int $limit, string $search = '', bool $includeArchived = false, ?User $owner = null): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Client $client): bool => ($includeArchived || !$client->isArchived())
                && ($owner === null || $client->getOwner() === $owner),
        ));
    }

    public function countBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null): int
    {
        return \count($this->findPage(1, PHP_INT_MAX, $search, $includeArchived, $owner));
    }

    public function save(Client $client): void
    {
        $this->saved[] = $client;
    }
}
