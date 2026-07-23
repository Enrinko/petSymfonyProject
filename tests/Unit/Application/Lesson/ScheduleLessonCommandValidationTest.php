<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Lesson;

use App\Application\Lesson\ScheduleLessonCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class ScheduleLessonCommandValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validTimes(): iterable
    {
        yield 'RFC3339 offset' => ['2026-07-20T15:00:00+00:00'];
        yield 'JS toISOString (ms + Z)' => ['2026-07-20T12:00:00.000Z'];
        yield 'ISO с оффсетом' => ['2026-07-20T15:00:00+03:00'];
    }

    #[DataProvider('validTimes')]
    public function testAcceptsIsoDateTimes(string $startsAt): void
    {
        $violations = self::validator()->validate(new ScheduleLessonCommand(1, null, $startsAt, 45, null));

        $timeErrors = array_filter(
            iterator_to_array($violations),
            static fn ($v): bool => $v->getPropertyPath() === 'startsAt',
        );

        self::assertCount(0, $timeErrors, sprintf('«%s» должно проходить как валидное время', $startsAt));
    }

    public function testRejectsGarbageTime(): void
    {
        $violations = self::validator()->validate(new ScheduleLessonCommand(1, null, 'не-дата', 45, null));

        self::assertGreaterThan(0, \count($violations));
    }

    private static function validator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
