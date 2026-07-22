<?php

declare(strict_types=1);

namespace App\Application\Client\Import;

/**
 * Разобранная строка CSV. Номер строки — по файлу (заголовок = 1),
 * чтобы ошибки в отчёте совпадали с тем, что видит человек в Excel.
 */
final readonly class ImportRow
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public int $line,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $comment,
        public array $tags,
    ) {
    }
}
