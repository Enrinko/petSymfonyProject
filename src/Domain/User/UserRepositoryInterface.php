<?php

declare(strict_types=1);

namespace App\Domain\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /**
     * @return list<User>
     */
    public function findPage(int $page, int $perPage, string $search = ''): array;

    public function countBySearch(string $search = ''): int;

    public function save(User $user): void;
}
