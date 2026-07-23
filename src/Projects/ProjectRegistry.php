<?php

declare(strict_types=1);

namespace App\Projects;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Реестр внешних проектов, читаемый из параметра app.external_projects
 * (config/projects.yaml). Единый источник для UI; не зависит от .gitmodules,
 * который исключён из Docker-образа.
 */
final class ProjectRegistry
{
    /**
     * Конфиг может быть и списком, и map'ой (ключи YAML) — all() нормализует.
     *
     * @param array<array-key, array<string, mixed>> $projects
     */
    public function __construct(
        #[Autowire('%app.external_projects%')]
        private array $projects,
    ) {
    }

    /**
     * @return list<ExternalProject>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $p): ExternalProject => new ExternalProject(
                key: (string) $p['key'],
                name: (string) $p['name'],
                description: (string) ($p['description'] ?? ''),
                url: (string) $p['url'],
                stack: (string) ($p['stack'] ?? 'Laravel'),
                icon: (string) ($p['icon'] ?? '🎻'),
                repo: isset($p['repo']) ? (string) $p['repo'] : null,
            ),
            array_values($this->projects),
        );
    }
}
