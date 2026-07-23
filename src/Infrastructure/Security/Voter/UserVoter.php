<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Voter;

use App\Domain\User\Role;
use App\Domain\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Права на конкретного пользователя (объектный уровень),
 * в отличие от access_control, который ограничивает только маршруты.
 *
 * @extends Voter<string, User>
 */
final class UserVoter extends Voter
{
    public const string MANAGE_ROLES = 'USER_MANAGE_ROLES';
    public const string MANAGE_STATUS = 'USER_MANAGE_STATUS';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::MANAGE_ROLES, self::MANAGE_STATUS], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$token->getUser() instanceof User) {
            return false;
        }

        if (!$this->authorizationChecker->isGranted(Role::Admin->value)) {
            return false;
        }

        // Роли деактивированного не редактируются: сначала верните его в строй.
        // Статус (активация) — можно всегда, иначе не выбраться из «неактивен».
        // ($subject типизирован дженериком Voter<string, User>)
        if ($attribute === self::MANAGE_ROLES && !$subject->isActive()) {
            return false;
        }

        return true;
    }
}
