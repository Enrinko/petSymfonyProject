<?php

declare(strict_types=1);

namespace App\Domain\Tag;

/**
 * Тег со счётчиком использований (для автодополнения и управления).
 */
final readonly class TagUsage
{
    public function __construct(
        public Tag $tag,
        public int $usageCount,
    ) {
    }
}
