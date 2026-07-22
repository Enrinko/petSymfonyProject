<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Instrument;

use App\Application\Instrument\CreateInstrumentCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class CreateInstrumentCommandValidationTest extends TestCase
{
    public function testValidCategoryPasses(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertCount(0, $validator->validate(new CreateInstrumentCommand('Труба', 'winds', 85)));
        self::assertCount(0, $validator->validate(new CreateInstrumentCommand('Орган', 'keyboard')));
    }

    public function testUnknownCategoryIsRejected(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new CreateInstrumentCommand('Терменвокс', 'чепуха'));

        self::assertGreaterThan(0, \count($violations));
        self::assertSame('category', $violations->get(0)->getPropertyPath());
    }

    public function testBlankNameIsRejected(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertGreaterThan(0, \count($validator->validate(new CreateInstrumentCommand('  ', 'vocal'))));
    }
}
