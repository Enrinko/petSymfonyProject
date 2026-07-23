<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tag;

use App\Domain\Tag\Exception\InvalidTagNameException;
use App\Domain\Tag\Tag;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{
    public function testNameIsNormalizedToLowercaseTrimmed(): void
    {
        self::assertSame('вокал', Tag::create('  Вокал ')->getName());
        self::assertSame('подготовка к конкурсу', Tag::create('Подготовка К Конкурсу')->getName());
    }

    public function testBlankNameIsRejected(): void
    {
        $this->expectException(InvalidTagNameException::class);

        Tag::create('   ');
    }

    public function testNormalizeOfIsReusableWithoutInstance(): void
    {
        self::assertSame('вокал', Tag::normalizeName(' ВОКАЛ '));
    }
}
