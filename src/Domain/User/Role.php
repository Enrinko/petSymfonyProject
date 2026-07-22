<?php

declare(strict_types=1);

namespace App\Domain\User;

enum Role: string
{
    case User = 'ROLE_USER';
    case Moderator = 'ROLE_MODERATOR';
    case Admin = 'ROLE_ADMIN';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
