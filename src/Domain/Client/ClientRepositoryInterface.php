<?php

declare(strict_types=1);

namespace App\Domain\Client;

use App\Domain\User\User;

interface ClientRepositoryInterface
{
    public function find(int $id): ?Client;

    /**
     * @param User|null    $owner ограничить выборку клиентами владельца (null — все)
     * @param list<string> $tags  фильтр по нормализованным именам тегов (ИЛИ)
     *
     * @return list<Client>
     */
    public function findPage(int $page, int $limit, string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): array;

    /**
     * @param list<string> $tags
     */
    public function countBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): int;

    public function save(Client $client): void;
}
