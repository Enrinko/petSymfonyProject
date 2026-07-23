<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Client;

use App\Application\Client\TagResolver;
use App\Domain\Tag\Tag;
use App\Tests\Fake\InMemoryTagRepository;
use PHPUnit\Framework\TestCase;

final class TagResolverTest extends TestCase
{
    public function testReusesExistingAndCreatesMissing(): void
    {
        $existing = Tag::create('вокал');
        $tags = new InMemoryTagRepository()->withTag(1, $existing);

        $resolved = new TagResolver($tags)->resolve(['Вокал', 'новый тег']);

        self::assertCount(2, $resolved);
        self::assertSame($existing, $resolved[0], 'Существующий тег переиспользован, а не создан дубль');
        self::assertSame('новый тег', $resolved[1]->getName());
        self::assertCount(1, $tags->saved, 'Сохранён только новый тег');
    }

    public function testNormalizesAndDeduplicatesInput(): void
    {
        $tags = new InMemoryTagRepository();

        $resolved = new TagResolver($tags)->resolve([' Вокал ', 'вокал', 'ВОКАЛ', '', '   ']);

        self::assertCount(1, $resolved);
        self::assertSame('вокал', $resolved[0]->getName());
    }

    public function testEmptyInputGivesEmptyList(): void
    {
        self::assertSame([], new TagResolver(new InMemoryTagRepository())->resolve([]));
    }
}
