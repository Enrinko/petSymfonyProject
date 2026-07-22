<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security\Voter;

use App\Domain\Client\Client;
use App\Domain\User\User;
use App\Infrastructure\Security\Voter\ClientVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ClientVoterTest extends TestCase
{
    public function testOwnerHasAccess(): void
    {
        $owner = User::register('teacher@example.com', 'hash');
        $client = Client::create('Анна', $owner, new \DateTimeImmutable());

        $voter = new ClientVoter($this->authorizationChecker(isModerator: false));
        $token = new UsernamePasswordToken($owner, 'main', ['ROLE_USER']);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $client, [ClientVoter::ACCESS]),
        );
    }

    public function testForeignRegularUserIsDenied(): void
    {
        $client = Client::create('Анна', User::register('teacher@example.com', 'hash'), new \DateTimeImmutable());
        $stranger = User::register('stranger@example.com', 'hash');

        $voter = new ClientVoter($this->authorizationChecker(isModerator: false));
        $token = new UsernamePasswordToken($stranger, 'main', ['ROLE_USER']);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $client, [ClientVoter::ACCESS]),
        );
    }

    public function testModeratorSeesAllClients(): void
    {
        $client = Client::create('Анна', User::register('teacher@example.com', 'hash'), new \DateTimeImmutable());
        $moderator = User::register('desk@example.com', 'hash');

        $voter = new ClientVoter($this->authorizationChecker(isModerator: true));
        $token = new UsernamePasswordToken($moderator, 'main', ['ROLE_MODERATOR']);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $client, [ClientVoter::ACCESS]),
        );
    }

    public function testAnonymousIsDenied(): void
    {
        $client = Client::create('Анна', User::register('teacher@example.com', 'hash'), new \DateTimeImmutable());

        $voter = new ClientVoter($this->authorizationChecker(isModerator: true));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), $client, [ClientVoter::ACCESS]),
        );
    }

    public function testAbstainsForForeignAttributeOrSubject(): void
    {
        $owner = User::register('teacher@example.com', 'hash');
        $client = Client::create('Анна', $owner, new \DateTimeImmutable());
        $voter = new ClientVoter($this->authorizationChecker(isModerator: false));
        $token = new UsernamePasswordToken($owner, 'main', ['ROLE_USER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $client, ['SOMETHING_ELSE']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), [ClientVoter::ACCESS]));
    }

    private function authorizationChecker(bool $isModerator): AuthorizationCheckerInterface
    {
        return new class($isModerator) implements AuthorizationCheckerInterface {
            public function __construct(private readonly bool $isModerator)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return $this->isModerator && $attribute === 'ROLE_MODERATOR';
            }
        };
    }
}
