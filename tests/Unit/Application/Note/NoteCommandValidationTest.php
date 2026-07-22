<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Note;

use App\Application\Note\AddNoteCommand;
use App\Application\Note\UpdateNoteCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class NoteCommandValidationTest extends TestCase
{
    public function testWhitespaceOnlyContentIsRejected(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertGreaterThan(
            0,
            \count($validator->validate(new AddNoteCommand("   \n  "))),
            'Пробельная заметка должна падать на валидации (422), а не в домене (500)',
        );
        self::assertGreaterThan(0, \count($validator->validate(new UpdateNoteCommand(1, '   '))));
        self::assertCount(0, $validator->validate(new AddNoteCommand('Разобрали гаммы')));
    }
}
