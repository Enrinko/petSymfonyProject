<?php

declare(strict_types=1);

namespace App\Tests\Unit\Projects;

use App\Projects\ExternalProject;
use App\Projects\ProjectRegistry;
use PHPUnit\Framework\TestCase;

final class ProjectRegistryTest extends TestCase
{
    public function testMapsConfiguredProjectsToDtos(): void
    {
        $registry = new ProjectRegistry([
            [
                'key' => 'app1',
                'name' => 'App One',
                'description' => 'Первое приложение',
                'url' => 'https://app1.localhost',
                'repo' => 'https://github.com/me/app1',
                'stack' => 'Laravel',
                'icon' => '🎻',
            ],
        ]);

        $projects = $registry->all();

        self::assertCount(1, $projects);
        self::assertSame('app1', $projects[0]->key);
        self::assertSame('App One', $projects[0]->name);
        self::assertSame('Первое приложение', $projects[0]->description);
        self::assertSame('https://app1.localhost', $projects[0]->url);
        self::assertSame('https://github.com/me/app1', $projects[0]->repo);
        self::assertSame('Laravel', $projects[0]->stack);
        self::assertSame('🎻', $projects[0]->icon);
    }

    public function testEmptyRegistryReturnsEmptyList(): void
    {
        self::assertSame([], (new ProjectRegistry([]))->all());
    }

    public function testAppliesDefaultsAndTreatsRepoAsOptional(): void
    {
        $registry = new ProjectRegistry([
            [
                'key' => 'app2',
                'name' => 'App Two',
                'url' => 'https://app2.localhost',
            ],
        ]);

        $project = $registry->all()[0];

        self::assertNull($project->repo);
        self::assertSame('', $project->description);
        self::assertSame('Laravel', $project->stack);
        self::assertSame('🎻', $project->icon);
    }

    public function testReturnsContiguousListEvenForAssociativeInput(): void
    {
        $registry = new ProjectRegistry([
            'first' => ['key' => 'a', 'name' => 'A', 'url' => 'https://a.localhost'],
            'second' => ['key' => 'b', 'name' => 'B', 'url' => 'https://b.localhost'],
        ]);

        $projects = $registry->all();

        self::assertCount(2, $projects);
        self::assertSame('a', $projects[0]->key);
        self::assertSame('b', $projects[1]->key);
    }
}
