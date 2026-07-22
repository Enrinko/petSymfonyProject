<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\User\Role;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeUserRolesCommand
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public int $userId,
        #[Assert\All([
            new Assert\Choice(callback: [Role::class, 'values'], message: 'Недопустимая роль.'),
        ])]
        public array $roles,
        public int $actorId,
    ) {
    }
}
