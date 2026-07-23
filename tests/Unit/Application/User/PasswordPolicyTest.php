<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\PasswordReset\ResetPasswordCommand;
use App\Application\User\RegisterUserCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Validation;

final class PasswordPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function commandsWithPassword(): iterable
    {
        yield 'register' => [RegisterUserCommand::class];
        yield 'reset' => [ResetPasswordCommand::class];
    }

    /**
     * @param class-string $commandClass
     */
    #[DataProvider('commandsWithPassword')]
    public function testPasswordFieldEnforcesPolicy(string $commandClass): void
    {
        $property = new \ReflectionProperty($commandClass, 'password');

        self::assertNotEmpty(
            $property->getAttributes(PasswordStrength::class),
            $commandClass . ': пароль должен проверяться на стойкость',
        );
        self::assertNotEmpty(
            $property->getAttributes(NotCompromisedPassword::class),
            $commandClass . ': пароль должен проверяться по базе утечек',
        );

        $lengths = $property->getAttributes(Length::class);
        self::assertNotEmpty($lengths);
        self::assertSame(10, $lengths[0]->newInstance()->min, $commandClass . ': минимум 10 символов');
    }

    /**
     * @param class-string $commandClass
     */
    #[DataProvider('commandsWithPassword')]
    public function testCompromisedCheckFailsOpenOnNetworkError(string $commandClass): void
    {
        $attribute = new \ReflectionProperty($commandClass, 'password')
            ->getAttributes(NotCompromisedPassword::class)[0]
            ->newInstance();

        self::assertTrue(
            $attribute->skipOnError,
            $commandClass . ': недоступность haveibeenpwned не должна блокировать операцию (fail-open)',
        );
    }

    public function testTrivialPasswordFailsStrengthCheck(): void
    {
        $violations = Validation::createValidator()->validate('password123', new PasswordStrength());

        self::assertGreaterThan(0, \count($violations));
    }

    public function testStrongPassphrasePassesStrengthCheck(): void
    {
        $violations = Validation::createValidator()->validate('verdi-Nabucco-1842-forte!', new PasswordStrength());

        self::assertCount(0, $violations);
    }
}
