<?php

declare(strict_types=1);

namespace App\Infrastructure\Csv;

/**
 * Защита от CSV formula injection (OWASP): ячейка, начинающаяся с
 * =, +, -, @, TAB или CR, исполняется Excel/LibreOffice как формула.
 * Ведущий апостроф — стандартный текстовый маркер Excel; наш импорт
 * снимает его обратно, поэтому экспорт→импорт остаётся без потерь.
 */
final class CsvInjectionGuard
{
    private const array FORMULA_STARTERS = ['=', '+', '-', '@', "\t", "\r"];

    private function __construct()
    {
    }

    public static function escape(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && \in_array($value[0], self::FORMULA_STARTERS, true)) {
            return "'" . $value;
        }

        return $value;
    }
}
