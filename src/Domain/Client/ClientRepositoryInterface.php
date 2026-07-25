<?php

declare(strict_types=1);

namespace App\Domain\Client;

use App\Domain\User\User;

interface ClientRepositoryInterface
{
    public function find(int $id): ?Client;

    /**
     * Порядок результата не гарантируется; отсутствующие id пропускаются.
     *
     * @param list<int> $ids
     *
     * @return list<Client>
     */
    public function findByIds(array $ids): array;

    /**
     * Поиск без учёта регистра (для дедупликации при импорте).
     */
    public function findByEmail(string $email): ?Client;

    /**
     * Потоковая выборка под экспорт — те же фильтры, без пагинации.
     *
     * @param list<string> $tags
     *
     * @return iterable<Client>
     */
    public function iterateBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = []): iterable;

    /**
     * @param User|null    $owner        ограничить выборку клиентами владельца (null — все)
     * @param list<string> $tags         фильтр по нормализованным именам тегов (ИЛИ)
     * @param int|null     $instrumentId фильтр по инструменту справочника
     *
     * @return list<Client>
     */
    public function findPage(int $page, int $limit, string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = [], ?int $instrumentId = null): array;

    /**
     * @param list<string> $tags
     */
    public function countBySearch(string $search = '', bool $includeArchived = false, ?User $owner = null, array $tags = [], ?int $instrumentId = null): int;

    /**
     * Число активных клиентов, созданных начиная с $since (для сводки дашборда).
     */
    public function countCreatedSince(\DateTimeImmutable $since, ?User $owner = null): int;

    public function save(Client $client): void;
}
