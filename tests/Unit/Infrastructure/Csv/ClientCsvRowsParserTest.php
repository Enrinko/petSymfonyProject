<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Csv;

use App\Infrastructure\Csv\ClientCsvFormatException;
use App\Infrastructure\Csv\ClientCsvRowsParser;
use PHPUnit\Framework\TestCase;

final class ClientCsvRowsParserTest extends TestCase
{
    public function testParsesCommaCsvWithEnglishHeaders(): void
    {
        $csv = "name,email,phone,comment,tags\n"
            . "Анна Скрипкина,anna@example.com,+7 900 000-00-00,вокал,\"вокал; конкурс\"\n"
            . 'Пётр Клавишев,,,,';

        $rows = new ClientCsvRowsParser()->parse($csv);

        self::assertCount(2, $rows);
        self::assertSame(2, $rows[0]->line, 'Первая строка данных — вторая строка файла');
        self::assertSame('Анна Скрипкина', $rows[0]->name);
        self::assertSame('anna@example.com', $rows[0]->email);
        self::assertSame(['вокал', 'конкурс'], $rows[0]->tags);
        self::assertSame('Пётр Клавишев', $rows[1]->name);
        self::assertNull($rows[1]->email);
        self::assertSame([], $rows[1]->tags);
    }

    public function testParsesSemicolonCsvWithRussianHeaders(): void
    {
        $csv = "Имя;Почта;Телефон\nАнна;anna@example.com;+79000000000";

        $rows = new ClientCsvRowsParser()->parse($csv);

        self::assertCount(1, $rows);
        self::assertSame('Анна', $rows[0]->name);
        self::assertSame('anna@example.com', $rows[0]->email);
        self::assertSame('+79000000000', $rows[0]->phone);
    }

    public function testConvertsWindows1251(): void
    {
        $csv = mb_convert_encoding("имя,комментарий\nАнна,вокал по вторникам", 'Windows-1251', 'UTF-8');

        $rows = new ClientCsvRowsParser()->parse($csv);

        self::assertSame('Анна', $rows[0]->name);
        self::assertSame('вокал по вторникам', $rows[0]->comment);
    }

    public function testStripsUtf8Bom(): void
    {
        $csv = "\u{FEFF}name\nАнна";

        $rows = new ClientCsvRowsParser()->parse($csv);

        self::assertSame('Анна', $rows[0]->name);
    }

    public function testLeadingApostropheTextMarkerIsStripped(): void
    {
        // Round-trip собственного экспорта: защита от формул добавляет «'»,
        // импорт обязан его снять (Excel-конвенция текстового маркера).
        $csv = "name,phone,comment\nАнна,'+7 (900) 123-45-67,'=не формула";

        $rows = new ClientCsvRowsParser()->parse($csv);

        self::assertSame('+7 (900) 123-45-67', $rows[0]->phone);
        self::assertSame('=не формула', $rows[0]->comment);
    }

    public function testMissingNameColumnIsRejected(): void
    {
        $this->expectException(ClientCsvFormatException::class);

        new ClientCsvRowsParser()->parse("email,phone\nanna@example.com,123");
    }

    public function testEmptyFileIsRejected(): void
    {
        $this->expectException(ClientCsvFormatException::class);

        new ClientCsvRowsParser()->parse('');
    }
}
