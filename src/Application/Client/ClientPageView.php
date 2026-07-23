<?php

declare(strict_types=1);

namespace App\Application\Client;

/**
 * Конверт списка по контракту openapi: { data, total } + пагинационные поля.
 */
final readonly class ClientPageView
{
    /**
     * @param list<ClientView> $data
     */
    public function __construct(
        public array $data,
        public int $total,
        public int $page,
        public int $limit,
    ) {
    }
}
