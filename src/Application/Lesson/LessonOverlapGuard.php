<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Lesson\Exception\LessonOverlapException;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonRepositoryInterface;
use App\Domain\Lesson\LessonStatus;
use App\Domain\User\User;

/**
 * У преподавателя не может быть двух пересекающихся занятий одновременно.
 * Отменённые занятия не мешают (время освобождается).
 */
final readonly class LessonOverlapGuard
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
    ) {
    }

    /**
     * @throws LessonOverlapException
     */
    public function assertFree(User $teacher, Lesson $candidate): void
    {
        // Дневного окна достаточно: занятие целиком лежит в своих сутках.
        $dayStart = $candidate->getStartsAt()->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        foreach ($this->lessons->findForTeacherBetween($teacher, $dayStart, $dayEnd) as $existing) {
            if ($existing->getId() === $candidate->getId()) {
                continue;
            }

            if ($existing->getStatus() === LessonStatus::Cancelled) {
                continue;
            }

            if ($existing->overlaps($candidate)) {
                throw new LessonOverlapException('The teacher already has a lesson at this time.');
            }
        }
    }
}
