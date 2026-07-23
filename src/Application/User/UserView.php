<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\User;

final readonly class UserView
{
    /**
     * @param list<string> $roles
     */
    private function __construct(
        public int $id,
        public string $email,
        public array $roles,
        public string $createdAt,
        public bool $isActive,
        public ?string $deactivatedAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            (int) $user->getId(),
            $user->getEmail(),
            $user->getRoles(),
            $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            $user->isActive(),
            $user->getDeactivatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
