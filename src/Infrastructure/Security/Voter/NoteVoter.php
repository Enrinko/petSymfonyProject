<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Voter;

use App\Domain\Note\Note;
use App\Domain\User\Role;
use App\Domain\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Редактирование/удаление заметки: автор в течение 24 часов, админ — всегда.
 * Само правило окна — доменное (Note::isManageableBy), voter только связывает
 * его с security-контекстом.
 *
 * @extends Voter<string, Note>
 */
final class NoteVoter extends Voter
{
    public const string MANAGE = 'NOTE_MANAGE';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof Note;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->authorizationChecker->isGranted(Role::Admin->value)) {
            return true;
        }

        return $subject->isManageableBy($user, new \DateTimeImmutable());
    }
}
