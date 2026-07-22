<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RescheduleLessonCommand
{
    public function __construct(
        public int $lessonId,
        #[Assert\NotBlank(message: 'Укажите время начала.')]
        // ISO 8601: принимаем и RFC3339 (+00:00), и вывод JS toISOString (миллисекунды + Z)
        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/',
            message: 'Некорректное время.',
        )]
        public string $startsAt,
        #[Assert\Positive(message: 'Длительность должна быть больше нуля.')]
        #[Assert\LessThanOrEqual(480, message: 'Слишком длинное занятие.')]
        public int $durationMinutes,
    ) {
    }
}
