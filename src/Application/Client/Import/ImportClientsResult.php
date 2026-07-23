<?php

declare(strict_types=1);

namespace App\Application\Client\Import;

final readonly class ImportClientsResult
{
    /**
     * @param list<array{line: int, message: string}> $errors
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public array $errors,
    ) {
    }
}
