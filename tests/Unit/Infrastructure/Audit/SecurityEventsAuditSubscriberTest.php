<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Audit;

use App\Domain\Audit\AuditAction;
use App\Domain\User\User;
use App\Infrastructure\Audit\SecurityEventsAuditSubscriber;
use App\Tests\Fake\FakeMetrics;
use App\Tests\Fake\InMemoryAuditEventRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Подписчик пишет входы/выходы напрямую через AuditEventRepositoryInterface,
 * минуя декорированный MetricsAuditLogger — поэтому счётчик метрик он
 * обязан вести сам (см. src/Infrastructure/Metrics/MetricsAuditLogger.php).
 */
final class SecurityEventsAuditSubscriberTest extends TestCase
{
    public function testLoginSuccessIsAuditedAndCounted(): void
    {
        $events = new InMemoryAuditEventRepository();
        $metrics = new FakeMetrics();
        $subscriber = new SecurityEventsAuditSubscriber($events, $metrics);

        $user = User::register('pianist@example.test', 'hashed');
        $request = Request::create('/api/login', 'POST');

        $subscriber->onLoginSuccess(new LoginSuccessEvent(
            $this->authenticator(),
            $this->passportFor($user),
            new UsernamePasswordToken($user, 'main'),
            $request,
            null,
            'main',
        ));

        self::assertCount(1, $events->saved);
        self::assertSame(AuditAction::LoginSucceeded->value, $events->saved[0]->getAction());

        self::assertSame(
            [['name' => 'audit_events_total', 'labels' => ['action' => AuditAction::LoginSucceeded->value]]],
            $metrics->increments,
        );
    }

    public function testLoginFailureIsAuditedAndCounted(): void
    {
        $events = new InMemoryAuditEventRepository();
        $metrics = new FakeMetrics();
        $subscriber = new SecurityEventsAuditSubscriber($events, $metrics);

        $request = Request::create('/api/login', 'POST');

        $subscriber->onLoginFailure(new LoginFailureEvent(
            new BadCredentialsException('Invalid credentials.'),
            $this->authenticator(),
            $request,
            null,
            'main',
        ));

        self::assertCount(1, $events->saved);
        self::assertSame(AuditAction::LoginFailed->value, $events->saved[0]->getAction());
        self::assertNull($events->saved[0]->getActorId());

        self::assertSame(
            [['name' => 'audit_events_total', 'labels' => ['action' => AuditAction::LoginFailed->value]]],
            $metrics->increments,
        );
    }

    public function testLogoutIsAuditedAndCounted(): void
    {
        $events = new InMemoryAuditEventRepository();
        $metrics = new FakeMetrics();
        $subscriber = new SecurityEventsAuditSubscriber($events, $metrics);

        $user = User::register('cellist@example.test', 'hashed');
        $request = Request::create('/api/logout', 'POST');

        $subscriber->onLogout(new LogoutEvent($request, new UsernamePasswordToken($user, 'main')));

        self::assertCount(1, $events->saved);
        self::assertSame(AuditAction::LoggedOut->value, $events->saved[0]->getAction());

        self::assertSame(
            [['name' => 'audit_events_total', 'labels' => ['action' => AuditAction::LoggedOut->value]]],
            $metrics->increments,
        );
    }

    private function passportFor(User $user): Passport
    {
        return new SelfValidatingPassport(new UserBadge($user->getEmail(), static fn (): User => $user));
    }

    private function authenticator(): AuthenticatorInterface
    {
        return new class implements AuthenticatorInterface {
            public function supports(Request $request): ?bool
            {
                return true;
            }

            public function authenticate(Request $request): Passport
            {
                throw new \LogicException('Not used in this test.');
            }

            public function createToken(Passport $passport, string $firewallName): TokenInterface
            {
                throw new \LogicException('Not used in this test.');
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }
}
