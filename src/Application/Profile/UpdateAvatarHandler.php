<?php

declare(strict_types=1);

namespace App\Application\Profile;

use App\Domain\User\AvatarStorageInterface;
use App\Domain\User\Exception\UnsupportedAvatarImageException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

final readonly class UpdateAvatarHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AvatarStorageInterface $avatars,
    ) {
    }

    /**
     * @throws UnsupportedAvatarImageException
     */
    public function __invoke(User $user, string $sourcePath): string
    {
        $userId = $user->getId();
        \assert($userId !== null, 'Avatar upload requires a persisted user.');

        $path = $this->avatars->store($userId, $sourcePath);
        $user->changeAvatar($path);
        $this->users->save($user);

        return $path;
    }

    public function remove(User $user): void
    {
        $current = $user->getAvatarPath();

        if ($current === null) {
            return;
        }

        $this->avatars->remove($current);
        $user->changeAvatar(null);
        $this->users->save($user);
    }
}
