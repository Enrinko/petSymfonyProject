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

    public function findPage(int $page, int $limit, string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): array
    {
        return array_values(array_filter(
            $this->byId,
            static function (Client $client) use ($includeArchived, $owner, $tags): bool {
                $tagNames = array_map(static fn ($tag) => $tag->getName(), $client->getTags());

                return ($includeArchived || !$client->isArchived())
                    && ($owner === null || $client->getOwner() === $owner)
                    && ($tags === [] || array_intersect($tags, $tagNames) !== []);
            },
        ));
    }

    public function countBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): int
    {
        return \count($this->findPage(1, PHP_INT_MAX, $search, $includeArchived, $owner, $tags));
    }

    public function save(Client $client): void
    {
        $this->saved[] = $client;
    }
}
