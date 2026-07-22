<?php

declare(strict_types=1);

namespace App\Domain\Tag;

interface TagRepositoryInterface
{
    public function find(int $id): ?Tag;

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
