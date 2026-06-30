<?php

declare(strict_types=1);

namespace App\Projects;

/**
 * Иммутабельное представление внешнего проекта (git-submodule в projects/<key>),
 * отображаемого на странице «Оркестр → Проекты».
 */
final readonly class ExternalProject
{
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public string $url,
        public string $stack,
        public string $icon,
        public ?string $repo = null,
    ) {
    }
}
