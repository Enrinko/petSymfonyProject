<?php

declare(strict_types=1);

namespace App\Infrastructure\Csv;

use App\Application\Client\Import\ImportRow;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;

/**
 * CSV → ImportRow[]: автоопределение кодировки (UTF-8 / Windows-1251)
 * и разделителя («,» / «;»), русские и английские заголовки.
 */
final class ClientCsvRowsParser
{
    /** Тот же потолок, что и в ImportClientsHandler — но применяем при разборе,
     *  чтобы не собрать миллион ImportRow в память до проверки (OOM-DoS). */
    private const int MAX_ROWS = 5000;

    private const array HEADER_ALIASES = [
        'name' => ['name', 'имя', 'фио', 'ученик'],
        'email' => ['email', 'e-mail', 'почта'],
        'phone' => ['phone', 'телефон', 'тел'],
        'comment' => ['comment', 'комментарий', 'заметка'],
        'tags' => ['tags', 'теги'],
    ];

    /**
     * @return list<ImportRow>
     *
     * @throws ClientCsvFormatException
     */
    public function parse(string $content): array
    {
        $content = $this->toUtf8($content);

        if (trim($content) === '') {
            throw new ClientCsvFormatException('Файл пуст.');
        }

        $reader = Reader::fromString($content);
        $reader->setDelimiter($this->detectDelimiter($content));
        $reader->setHeaderOffset(0);

        // getHeader() бросает SyntaxError при повторяющихся колонках (частый экспорт
        // из Excel с двумя пустыми заголовками) — переводим в понятную 422, а не 500
        try {
            $header = array_values($reader->getHeader());
        } catch (CsvException $exception) {
            throw new ClientCsvFormatException('Некорректные заголовки CSV: повторяющиеся или пустые колонки.');
        }

        $map = $this->mapHeaders($header);

        if (!isset($map['name'])) {
            throw new ClientCsvFormatException('Не найдена колонка с именем (name / имя / ФИО).');
        }

        $rows = [];

        try {
            $records = $reader->getRecords();
        } catch (CsvException $exception) {
            throw new ClientCsvFormatException('Не удалось прочитать CSV.');
        }

        foreach ($records as $offset => $record) {
            if (\count($rows) >= self::MAX_ROWS) {
                throw new ClientCsvFormatException(sprintf('Слишком много строк (максимум %d).', self::MAX_ROWS));
            }

            $get = static function (string $field) use ($map, $record): ?string {
                $value = isset($map[$field]) ? trim((string) ($record[$map[$field]] ?? '')) : '';

                // Excel-маркер текста (и наша же защита от формул при экспорте)
                if (str_starts_with($value, "'")) {
                    $value = substr($value, 1);
                }

                return $value === '' ? null : $value;
            };

            $tagsCell = $get('tags');

            $rows[] = new ImportRow(
                $offset + 1,
                $get('name') ?? '',
                $get('email'),
                $get('phone'),
                $get('comment'),
                $tagsCell === null
                    ? []
                    : array_values(array_filter(array_map('trim', preg_split('/[;|]/', $tagsCell) ?: []))),
            );
        }

        return $rows;
    }

    private function toUtf8(string $content): string
    {
        // BOM UTF-8
        if (str_starts_with($content, "\u{FEFF}")) {
            $content = substr($content, 3);
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
        }

        return $content;
    }

    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n") ?: '';

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * @param list<string> $header
     *
     * @return array<string, string> внутреннее поле → исходное имя колонки
     */
    private function mapHeaders(array $header): array
    {
        $map = [];

        foreach ($header as $column) {
            $normalized = mb_strtolower(trim($column));

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (!isset($map[$field]) && \in_array($normalized, $aliases, true)) {
                    $map[$field] = $column;
                }
            }
        }

        return $map;
    }
}
