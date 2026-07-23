<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @var array<int, User>
     */
    private array $byId = [];

    /**
     * @var list<User>
     */
    public array $saved = [];

    public function withUser(int $id, User $user): self
    {
        $this->byId[$id] = $user;

        return $this;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        foreach ($this->saved as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?User
    {
        return $this->byId[$id] ?? null;
    }

    public function findPage(int $page, int $perPage, string $search = ''): array
    {
        return array_values($this->byId);
    }

    public function countBySearch(string $search = ''): int
    {
        return \count($this->byId);
    }

    public function countActiveAdminsExcept(int $excludedUserId): int
    {
        $count = 0;

        foreach ($this->byId as $id => $user) {
            if ($id !== $excludedUserId && $user->isActive() && $user->isAdmin()) {
                ++$count;
            }
        }

        return $count;
    }

    public function save(User $user): void
    {
        $this->saved[] = $user;
    }
}
