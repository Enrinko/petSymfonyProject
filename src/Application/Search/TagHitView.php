<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Tag\TagUsage;

final readonly class TagHitView
{
    private function __construct(
        public string $name,
        public int $usageCount,
    ) {
    }

    public static function fromUsage(TagUsage $usage): self
    {
        return new self($usage->tag->getName(), $usage->usageCount);
    }
}
