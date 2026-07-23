<?php

declare(strict_types=1);

namespace App\Domain\Tag;

use App\Domain\User\User;

interface TagRepositoryInterface
{
    public function find(int $id): ?Tag;

    /**
     * Теги, чьё имя содержит подстроку и у которых есть хотя бы один
     * видимый активный клиент. usageCount — число таких клиентов.
     * owner ограничивает видимость (null — без ограничений).
     *
     * @return list<TagUsage>
     */
    public function searchVisibleTop(string $query, ?User $owner, int $limit): array;

    /**
     * @param list<string> $names нормализованные имена
     *
     * @return list<Tag>
     */
    public function findByNames(array $names): array;

    /**
     * @return list<TagUsage>
     */
    public function findAllWithUsage(): array;

    public function save(Tag $tag): void;

    public function remove(Tag $tag): void;
}
