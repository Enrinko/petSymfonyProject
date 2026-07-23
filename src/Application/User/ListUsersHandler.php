<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

final readonly class ListUsersHandler
{
    private const int MAX_PER_PAGE = 100;

    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function __invoke(ListUsersQuery $query): UserPageView
    {
        $page = max(1, $query->page);
        $perPage = min(max(1, $query->perPage), self::MAX_PER_PAGE);
        $search = trim($query->search);

        $users = array_map(
            static fn (User $user): UserView => UserView::fromUser($user),
            $this->users->findPage($page, $perPage, $search),
        );

        return new UserPageView(
            $users,
            $this->users->countBySearch($search),
            $page,
            $perPage,
        );
    }
}
