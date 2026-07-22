<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Lesson\Exception\LessonNotFoundException;
use App\Domain\Lesson\Exception\LessonOverlapException;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonRepositoryInterface;
use App\Domain\User\User;

final readonly class RescheduleLessonHandler
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
    ) {
    }

    /**
     * @throws LessonNotFoundException
     * @throws LessonOverlapException
     */
    public function __invoke(RescheduleLessonCommand $command, User $teacher): Lesson
    {
        $lesson = $this->ownedLesson($command->lessonId, $teacher);

        $lesson->reschedule(new \DateTimeImmutable($command->startsAt), $command->durationMinutes);

        // Проверка пересечений исключает само занятие (по id)
        new LessonOverlapGuard($this->lessons)->assertFree($teacher, $lesson);

        $this->lessons->save($lesson);

        return $lesson;
    }

    private function ownedLesson(int $id, User $teacher): Lesson
    {
        $lesson = $this->lessons->find($id);

        if ($lesson === null || !$lesson->getTeacher()->isSameAs($teacher)) {
            throw new LessonNotFoundException(sprintf('Lesson #%d is not available.', $id));
        }

        return $lesson;
    }
}
