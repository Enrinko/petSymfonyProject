<?php

declare(strict_types=1);

namespace App\Application\User;

final readonly class ListUsersQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public string $search = '',
    ) {
    }
}
