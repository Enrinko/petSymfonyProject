<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security\Voter;

use App\Domain\User\User;
use App\Infrastructure\Security\Voter\UserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class UserVoterTest extends TestCase
{
    public function testAdminCanManageRoles(): void
    {
        $voter = new UserVoter($this->authorizationChecker(isAdmin: true));
        $token = new UsernamePasswordToken(User::register('admin@example.com', 'hash'), 'main', ['ROLE_ADMIN']);

        $result = $voter->vote($token, User::register('target@example.com', 'hash'), [UserVoter::MANAGE_ROLES]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testRegularUserCannotManageRoles(): void
    {
        $voter = new UserVoter($this->authorizationChecker(isAdmin: false));
        $token = new UsernamePasswordToken(User::register('user@example.com', 'hash'), 'main', ['ROLE_USER']);

        $result = $voter->vote($token, User::register('target@example.com', 'hash'), [UserVoter::MANAGE_ROLES]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAnonymousIsDenied(): void
    {
        $voter = new UserVoter($this->authorizationChecker(isAdmin: true));

        $result = $voter->vote(new NullToken(), User::register('target@example.com', 'hash'), [UserVoter::MANAGE_ROLES]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAbstainsForForeignAttributeOrSubject(): void
    {
        $voter = new UserVoter($this->authorizationChecker(isAdmin: true));
        $token = new UsernamePasswordToken(User::register('admin@example.com', 'hash'), 'main', ['ROLE_ADMIN']);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($token, User::register('target@example.com', 'hash'), ['SOMETHING_ELSE']),
        );
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($token, new \stdClass(), [UserVoter::MANAGE_ROLES]),
        );
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
