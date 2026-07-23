<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Client;

use App\Application\Client\CreateClientCommand;
use App\Application\Client\UpdateClientCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Validation;

final class ClientCommandValidationTest extends TestCase
{
    private static function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function commands(): iterable
    {
        yield 'create' => [new CreateClientCommand('Анна', 'not-an-email', 'abc-def', null)];
        yield 'update' => [new UpdateClientCommand(1, 'Анна', 'not-an-email', 'abc-def', null)];
    }

    #[DataProvider('commands')]
    public function testInvalidEmailAndPhoneAreRejected(object $command): void
    {
        $violations = self::validator()->validate($command);

        $fields = [];
        foreach ($violations as $violation) {
            $fields[] = $violation->getPropertyPath();
        }

        self::assertContains('email', $fields, 'Кривой email должен давать ошибку поля email');
        self::assertContains('phone', $fields, 'Телефон из букв должен давать ошибку поля phone');
    }

    public function testValidPhoneFormatsPass(): void
    {
        foreach (['+7 900 000-00-00', '89001234567', '+49 (30) 1234-567'] as $phone) {
            $violations = self::validator()->validate(new CreateClientCommand('Анна', null, $phone, null));

            self::assertCount(0, $violations, sprintf('Телефон "%s" должен проходить', $phone));
        }
    }

    public function testTooShortPhoneIsRejected(): void
    {
        $violations = self::validator()->validate(new CreateClientCommand('Анна', null, '12', null));

        self::assertGreaterThan(0, \count($violations));
    }

    public function testEmptyOptionalFieldsAreAccepted(): void
    {
        $violations = self::validator()->validate(new CreateClientCommand('Анна', null, null, null));

        self::assertCount(0, $violations);
    }
}
