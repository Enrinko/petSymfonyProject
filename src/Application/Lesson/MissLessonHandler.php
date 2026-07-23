<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Lesson\Exception\LessonNotFoundException;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonRepositoryInterface;
use App\Domain\User\User;

/** Ученик не пришёл: слот закрывается с пропуском. */
final readonly class MissLessonHandler
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
    ) {
    }

    /**
     * @throws LessonNotFoundException
     */
    public function __invoke(int $lessonId, User $teacher): Lesson
    {
        $lesson = $this->lessons->find($lessonId);

        if ($lesson === null || !$lesson->getTeacher()->isSameAs($teacher)) {
            throw new LessonNotFoundException(sprintf('Lesson #%d is not available.', $lessonId));
        }

        $lesson->markMissed(new \DateTimeImmutable());
        $this->lessons->save($lesson);

        return $lesson;
    }
}
