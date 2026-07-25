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

    public function findByIds(array $ids): array
    {
        return array_values(array_filter(array_map(fn (int $id): ?Client => $this->byId[$id] ?? null, $ids)));
    }

    public function findByEmail(string $email, ?User $owner = null): ?Client
    {
        foreach ([...array_values($this->byId), ...$this->saved] as $client) {
            if ($client->isArchived()) {
                continue;
            }

            if ($owner !== null && $client->getOwner() !== $owner) {
                continue;
            }

            if ($client->getEmail() !== null && strcasecmp($client->getEmail(), $email) === 0) {
                return $client;
            }
        }

        return null;
    }

    public function iterateBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): iterable
    {
        yield from $this->findPage(1, PHP_INT_MAX, $search, $includeArchived, $owner, $tags);
    }

    public function findPage(int $page, int $limit, string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = [], ?int $instrumentId = null): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static function (Client $client) use ($search, $includeArchived, $owner, $tags, $instrumentId): bool {
                $tagNames = array_map(static fn ($tag) => $tag->getName(), $client->getTags());
                $instrumentIds = array_map(static fn ($i) => $i->getId(), $client->getInstruments());
                $haystack = $client->getName() . ' ' . ($client->getEmail() ?? '') . ' ' . ($client->getPhone() ?? '');

                return ($includeArchived || !$client->isArchived())
                    && ($owner === null || $client->getOwner() === $owner)
                    && ($tags === [] || array_intersect($tags, $tagNames) !== [])
                    && ($instrumentId === null || \in_array($instrumentId, $instrumentIds, true))
                    && ($search === '' || mb_stripos($haystack, $search) !== false);
            },
        ));

        return \array_slice($matches, ($page - 1) * $limit, $limit);
    }

    public function countBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = [], ?int $instrumentId = null): int
    {
        return \count($this->findPage(1, PHP_INT_MAX, $search, $includeArchived, $owner, $tags, $instrumentId));
    }

    public function countCreatedSince(\DateTimeImmutable $since, ?User $owner = null): int
    {
        return \count(array_filter(
            $this->byId,
            static fn (Client $client): bool => !$client->isArchived()
                && $client->getCreatedAt() >= $since
                && ($owner === null || $client->getOwner() === $owner),
        ));
    }

    public function save(Client $client): void
    {
        if ($client->getId() === null) {
            // Как flush в Doctrine: после save у сущности появляется id
            new \ReflectionProperty($client, 'id')->setValue($client, $this->idSequence++);
        }

        $this->saved[] = $client;
    }

    private int $idSequence = 1;
}
