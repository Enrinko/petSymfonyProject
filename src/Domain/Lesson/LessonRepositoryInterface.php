<?php

declare(strict_types=1);

namespace App\Domain\Lesson;

use App\Domain\Client\Client;
use App\Domain\User\User;

interface LessonRepositoryInterface
{
    public function find(int $id): ?Lesson;

    /**
     * Занятия преподавателя в полуинтервале [from; to) — для недельной сетки.
     *
     * @return list<Lesson>
     */
    public function findForTeacherBetween(User $teacher, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * Ближайшие запланированные занятия ученика (startsAt >= now), свежие снизу.
     *
     * @return list<Lesson>
     */
    public function findUpcomingForClient(Client $client, \DateTimeImmutable $now, int $limit): array;

    public function save(Lesson $lesson): void;

    public function remove(Lesson $lesson): void;
}
