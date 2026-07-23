<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Lesson\Lesson;

final readonly class LessonView
{
    private function __construct(
        public int $id,
        public int $clientId,
        public string $clientName,
        public ?int $instrumentId,
        public ?string $instrumentName,
        public ?string $instrumentCategory,
        public string $startsAt,
        public string $endsAt,
        public int $durationMinutes,
        public string $status,
        public string $statusLabel,
        public ?string $comment,
        public ?string $cancelReason,
        public ?string $attendance,
        public ?string $attendanceLabel,
    ) {
    }

    public static function fromLesson(Lesson $lesson): self
    {
        $instrument = $lesson->getInstrument();

        return new self(
            (int) $lesson->getId(),
            (int) $lesson->getClient()->getId(),
            $lesson->getClient()->getName(),
            $instrument?->getId(),
            $instrument?->getName(),
            $instrument?->getCategory()->value,
            $lesson->getStartsAt()->format(\DateTimeInterface::ATOM),
            $lesson->getEndsAt()->format(\DateTimeInterface::ATOM),
            $lesson->getDurationMinutes(),
            $lesson->getStatus()->value,
            $lesson->getStatus()->label(),
            $lesson->getComment(),
            $lesson->getCancelReason(),
            $lesson->getAttendance()?->value,
            $lesson->getAttendance()?->label(),
        );
    }
}
