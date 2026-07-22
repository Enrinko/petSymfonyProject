<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Tag\Tag;
use App\Domain\Tag\TagRepositoryInterface;
use App\Domain\Tag\TagUsage;

final class InMemoryTagRepository implements TagRepositoryInterface
{
    /**
     * @var array<int, Tag>
     */
    private array $byId = [];

    /**
     * @var list<Tag>
     */
    public array $saved = [];

    /**
     * @var list<Tag>
     */
    public array $removed = [];

    public function withTag(int $id, Tag $tag): self
    {
        $this->byId[$id] = $tag;

        return $this;
    }

    public function find(int $id): ?Tag
    {
        return $this->byId[$id] ?? null;
    }

    public function findByNames(array $names): array
    {
        $all = [...array_values($this->byId), ...$this->saved];

        return array_values(array_filter(
            $all,
            static fn (Tag $tag): bool => \in_array($tag->getName(), $names, true),
        ));
    }

    public function findAllWithUsage(): array
    {
        return array_map(
            static fn (Tag $tag): TagUsage => new TagUsage($tag, 0),
            array_values($this->byId),
        );
    }

    public function save(Tag $tag): void
    {
        $this->saved[] = $tag;
    }

    public function remove(Tag $tag): void
    {
        $this->removed[] = $tag;
    }
}
