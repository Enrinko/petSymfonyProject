<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security\Voter;

use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\User\User;
use App\Infrastructure\Security\Voter\NoteVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class NoteVoterTest extends TestCase
{
    public function testAuthorManagesFreshNote(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $note = Note::create(self::client($author), $author, 'Запись', new \DateTimeImmutable('-1 hour'));

        $voter = new NoteVoter($this->authorizationChecker(isAdmin: false));
        $token = new UsernamePasswordToken($author, 'main', ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $note, [NoteVoter::MANAGE]));
    }

    public function testAuthorCannotManageAfterWindow(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $note = Note::create(self::client($author), $author, 'Запись', new \DateTimeImmutable('-25 hours'));

        $voter = new NoteVoter($this->authorizationChecker(isAdmin: false));
        $token = new UsernamePasswordToken($author, 'main', ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $note, [NoteVoter::MANAGE]));
    }

    public function testAdminManagesForeignOldNote(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $note = Note::create(self::client($author), $author, 'Запись', new \DateTimeImmutable('-30 days'));
        $admin = User::register('director@example.com', 'hash');

        $voter = new NoteVoter($this->authorizationChecker(isAdmin: true));
        $token = new UsernamePasswordToken($admin, 'main', ['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $note, [NoteVoter::MANAGE]));
    }

    public function testStrangerIsDenied(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $note = Note::create(self::client($author), $author, 'Запись', new \DateTimeImmutable('-1 minute'));
        $stranger = User::register('other@example.com', 'hash');

        $voter = new NoteVoter($this->authorizationChecker(isAdmin: false));
        $token = new UsernamePasswordToken($stranger, 'main', ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $note, [NoteVoter::MANAGE]));
    }

    public function testAbstainsForForeignAttributeOrSubject(): void
    {
        $author = User::register('teacher@example.com', 'hash');
        $note = Note::create(self::client($author), $author, 'Запись', new \DateTimeImmutable());
        $voter = new NoteVoter($this->authorizationChecker(isAdmin: false));
        $token = new UsernamePasswordToken($author, 'main', ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $note, ['SOMETHING_ELSE']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), [NoteVoter::MANAGE]));
    }

    private static function client(User $owner): Client
    {
        return Client::create('Анна', $owner, new \DateTimeImmutable('2026-07-01 09:00:00'));
    }

    private function authorizationChecker(bool $isAdmin): AuthorizationCheckerInterface
    {
        return new class($isAdmin) implements AuthorizationCheckerInterface {
            public function __construct(private readonly bool $isAdmin)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return $this->isAdmin && $attribute === 'ROLE_ADMIN';
            }
        };
    }
}
