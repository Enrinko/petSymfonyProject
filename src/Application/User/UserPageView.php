<?php

declare(strict_types=1);

namespace App\Application\User;

final readonly class UserPageView
{
    /**
     * @param list<UserView> $users
     */
    public function __construct(
        public array $users,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
