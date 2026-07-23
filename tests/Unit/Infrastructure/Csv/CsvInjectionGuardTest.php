<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Csv;

use App\Infrastructure\Csv\CsvInjectionGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvInjectionGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cells(): iterable
    {
        yield 'формула =' => ['=SUM(A1:A9)', "'=SUM(A1:A9)"];
        yield 'формула @' => ['@cmd', "'@cmd"];
        yield 'плюс (телефон)' => ['+7 (900) 123-45-67', "'+7 (900) 123-45-67"];
        yield 'минус' => ['-2+3', "'-2+3"];
        yield 'таб' => ["\t=1", "'\t=1"];
        yield 'CR' => ["\r=1", "'\r=1"];
        yield 'обычный текст' => ['Анна Скрипкина', 'Анна Скрипкина'];
        yield 'пустая строка' => ['', ''];
        yield 'цифры' => ['79001234567', '79001234567'];
    }

    #[DataProvider('cells')]
    public function testEscapeNeutralizesFormulaStarters(string $raw, string $expected): void
    {
        self::assertSame($expected, CsvInjectionGuard::escape($raw));
    }

    public function testNullBecomesEmptyString(): void
    {
        self::assertSame('', CsvInjectionGuard::escape(null));
    }
}
