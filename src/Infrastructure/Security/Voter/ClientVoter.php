<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Voter;

use App\Domain\Client\Client;
use App\Domain\User\Role;
use App\Domain\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Доступ к конкретному клиенту: преподаватель видит только своих учеников,
 * модератор (администратор зала) и админ (директор) — всех.
 *
 * @extends Voter<string, Client>
 */
final class ClientVoter extends Voter
{
    public const string ACCESS = 'CLIENT_ACCESS';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ACCESS && $subject instanceof Client;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->authorizationChecker->isGranted(Role::Moderator->value)) {
            return true;
        }

        $owner = $subject->getOwner();

        // Сравнение по id, когда сущности уже сохранены; по идентичности — в остальных случаях.
        return $owner === $user
            || ($owner->getId() !== null && $owner->getId() === $user->getId());
    }
}
