<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Tag\Tag;
use App\Domain\Tag\TagRepositoryInterface;

/**
 * Имена тегов из формы → сущности: существующие переиспользуются,
 * недостающие создаются на лету (лёгкость инструмента — без отдельной
 * формы «создать тег»).
 */
final readonly class TagResolver
{
    public function __construct(
        private TagRepositoryInterface $tags,
    ) {
    }

    /**
     * @param list<string> $names
     *
     * @return list<Tag>
     */
    public function resolve(array $names): array
    {
        $normalized = [];

        foreach ($names as $name) {
            $name = mb_strtolower(trim($name));

            if ($name !== '' && !\in_array($name, $normalized, true)) {
                $normalized[] = $name;
            }
        }

        if ($normalized === []) {
            return [];
        }

        $existing = [];

        foreach ($this->tags->findByNames($normalized) as $tag) {
            $existing[$tag->getName()] = $tag;
        }

        $resolved = [];

        foreach ($normalized as $name) {
            $tag = $existing[$name] ?? null;

            if ($tag === null) {
                $tag = Tag::create($name);
                $this->tags->save($tag);
            }

            $resolved[] = $tag;
        }

        return $resolved;
    }
}
