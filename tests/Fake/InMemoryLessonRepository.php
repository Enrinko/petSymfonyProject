<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Client\Client;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonRepositoryInterface;
use App\Domain\User\User;

final class InMemoryLessonRepository implements LessonRepositoryInterface
{
    /**
     * @var array<int, Lesson>
     */
    private array $byId = [];

    /**
     * @var list<Lesson>
     */
    public array $saved = [];

    /**
     * @var list<Lesson>
     */
    public array $removed = [];

    public function withLesson(int $id, Lesson $lesson): self
    {
        new \ReflectionProperty(Lesson::class, 'id')->setValue($lesson, $id);
        $this->byId[$id] = $lesson;

        return $this;
    }

    public function find(int $id): ?Lesson
    {
        return $this->byId[$id] ?? null;
    }

    public function findForTeacherBetween(User $teacher, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Lesson $lesson): bool => $lesson->getTeacher() === $teacher
                && $lesson->getStartsAt() >= $from
                && $lesson->getStartsAt() < $to,
        ));
    }

    public function findUpcomingForClient(Client $client, \DateTimeImmutable $now, int $limit): array
    {
        $matches = array_values(array_filter(
            $this->byId,
            static fn (Lesson $lesson): bool => $lesson->getClient() === $client && $lesson->getStartsAt() >= $now,
        ));

        return \array_slice($matches, 0, $limit);
    }

    public function findRecentClosedForClient(Client $client, int $limit): array
    {
        $closed = array_values(array_filter(
            $this->byId,
            static fn (Lesson $lesson): bool => $lesson->getClient() === $client
                && $lesson->getStatus() !== \App\Domain\Lesson\LessonStatus::Planned,
        ));

        usort($closed, static fn (Lesson $a, Lesson $b): int => $b->getStartsAt() <=> $a->getStartsAt());

        return \array_slice($closed, 0, $limit);
    }

    public function findClosedForClientSince(Client $client, \DateTimeImmutable $since): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Lesson $lesson): bool => $lesson->getClient() === $client
                && $lesson->getStatus() !== \App\Domain\Lesson\LessonStatus::Planned
                && $lesson->getStartsAt() >= $since,
        ));
    }

    public function save(Lesson $lesson): void
    {
        $this->saved[] = $lesson;

        if ($lesson->getId() !== null) {
            $this->byId[$lesson->getId()] = $lesson;
        }
    }

    public function remove(Lesson $lesson): void
    {
        $this->removed[] = $lesson;
    }
}
