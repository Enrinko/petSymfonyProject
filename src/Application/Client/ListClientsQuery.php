<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\User\User;

final readonly class ListClientsQuery
{
    /**
     * @param User|null    $owner        null — без owner-скоупа (модератор/админ видят всех)
     * @param list<string> $tags         фильтр по тегам (логика ИЛИ)
     * @param int|null     $instrumentId фильтр по инструменту справочника
     */
    public function __construct(
        public int $page = 1,
        public int $limit = 20,
        public string $search = '',
        public bool $includeArchived = false,
        public ?User $owner = null,
        public array $tags = [],
        public ?int $instrumentId = null,
    ) {
    }
}
