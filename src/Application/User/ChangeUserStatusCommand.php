<?php

declare(strict_types=1);

namespace App\Application\User;

final readonly class ChangeUserStatusCommand
{
    public function __construct(
        public int $userId,
        public int $actorId,
        public bool $active,
    ) {
    }
}
