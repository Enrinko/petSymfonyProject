<?php

declare(strict_types=1);

namespace App\Domain\Session;

interface UserSessionRepositoryInterface
{
    public function save(UserSession $session): void;

    public function findByHash(string $sessionIdHash): ?UserSession;

    /**
     * @return list<UserSession> свежие сверху
     */
    public function findByUser(int $userId): array;

    public function removeByHash(string $sessionIdHash): void;

    /** @return int сколько записей завершено */
    public function removeAllForUserExcept(int $userId, string $keepSessionIdHash): int;

    /** @return int удалено записей, по которым давно не было активности */
    public function removeStale(\DateTimeImmutable $threshold): int;
}
