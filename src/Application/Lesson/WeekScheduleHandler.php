<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonRepositoryInterface;
use App\Domain\User\User;

/**
 * Занятия преподавателя на неделю (понедельник–воскресенье), содержащую $anchor.
 */
final readonly class WeekScheduleHandler
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
    ) {
    }

    /**
     * @return array{weekStart: string, weekEnd: string, lessons: list<LessonView>}
     */
    public function __invoke(User $teacher, \DateTimeImmutable $anchor): array
    {
        $weekStart = $anchor->modify('monday this week')->setTime(0, 0);
        $weekEnd = $weekStart->modify('+7 days');

        $lessons = array_map(
            static fn (Lesson $lesson): LessonView => LessonView::fromLesson($lesson),
            $this->lessons->findForTeacherBetween($teacher, $weekStart, $weekEnd),
        );

        return [
            'weekStart' => $weekStart->format('Y-m-d'),
            'weekEnd' => $weekEnd->modify('-1 day')->format('Y-m-d'),
            'lessons' => $lessons,
        ];
    }
}
