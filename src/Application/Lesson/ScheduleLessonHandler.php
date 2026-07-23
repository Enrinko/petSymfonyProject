<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Client\Exception\ClientNotFoundException;
use App\Domain\Instrument\InstrumentRepositoryInterface;
use App\Domain\Lesson\Exception\LessonOverlapException;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonRepositoryInterface;
use App\Domain\User\User;

final readonly class ScheduleLessonHandler
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private ClientRepositoryInterface $clients,
        private InstrumentRepositoryInterface $instruments,
    ) {
    }

    /**
     * @throws ClientNotFoundException чужой/несуществующий ученик
     * @throws LessonOverlapException
     */
    public function __invoke(ScheduleLessonCommand $command, User $teacher): Lesson
    {
        $client = $this->clients->find($command->clientId);

        // Планировать можно только своим ученикам (владелец = преподаватель)
        if ($client === null || !$client->getOwner()->isSameAs($teacher)) {
            throw new ClientNotFoundException(sprintf('Client #%d is not available.', $command->clientId));
        }

        $instrument = $command->instrumentId !== null
            ? $this->instruments->find($command->instrumentId)
            : null;

        $lesson = Lesson::schedule(
            $teacher,
            $client,
            $instrument,
            new \DateTimeImmutable($command->startsAt),
            $command->durationMinutes,
            $command->comment,
        );

        new LessonOverlapGuard($this->lessons)->assertFree($teacher, $lesson);

        $this->lessons->save($lesson);

        return $lesson;
    }
}
