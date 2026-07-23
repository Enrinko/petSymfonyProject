<?php

declare(strict_types=1);

namespace App\Domain\Lesson;

use App\Domain\Client\Client;
use App\Domain\Instrument\Instrument;
use App\Domain\Lesson\Exception\InvalidLessonException;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Занятие — урок с учеником в конкретное время.
 * Инварианты: длительность > 0; провести можно только после начала;
 * отмена требует причины; завершённое/отменённое занятие не переносится.
 */
#[ORM\Entity]
#[ORM\Table(name: 'lesson')]
#[ORM\Index(name: 'idx_lesson_teacher_starts', columns: ['teacher_id', 'starts_at'])]
class Lesson
{
    private const int MAX_DURATION = 480;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $teacher;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: Instrument::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Instrument $instrument;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private int $durationMinutes;

    #[ORM\Column(enumType: LessonStatus::class)]
    private LessonStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(enumType: Attendance::class, nullable: true)]
    private ?Attendance $attendance = null;

    /** Когда отправлено напоминание (идемпотентность рассылки). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    private function __construct(
        User $teacher,
        Client $client,
        ?Instrument $instrument,
        \DateTimeImmutable $startsAt,
        int $durationMinutes,
        ?string $comment,
    ) {
        $this->teacher = $teacher;
        $this->client = $client;
        $this->instrument = $instrument;
        $this->startsAt = $startsAt;
        $this->durationMinutes = self::assertDuration($durationMinutes);
        $this->status = LessonStatus::Planned;
        $this->comment = self::normalizeComment($comment);
    }

    public static function schedule(
        User $teacher,
        Client $client,
        ?Instrument $instrument,
        \DateTimeImmutable $startsAt,
        int $durationMinutes,
        ?string $comment = null,
    ): self {
        return new self($teacher, $client, $instrument, $startsAt, $durationMinutes, $comment);
    }

    public function reschedule(\DateTimeImmutable $startsAt, int $durationMinutes): void
    {
        if ($this->status !== LessonStatus::Planned) {
            throw new InvalidLessonException('Only a planned lesson can be rescheduled.');
        }

        $this->startsAt = $startsAt;
        $this->durationMinutes = self::assertDuration($durationMinutes);
    }

    public function updateDetails(?Instrument $instrument, ?string $comment): void
    {
        $this->instrument = $instrument;
        $this->comment = self::normalizeComment($comment);
    }

    public function complete(\DateTimeImmutable $now): void
    {
        $this->closeWith(Attendance::Attended, $now);
    }

    /** Слот прошёл, ученик не пришёл: занятие закрывается с пропуском. */
    public function markMissed(\DateTimeImmutable $now): void
    {
        $this->closeWith(Attendance::Missed, $now);
    }

    public function cancel(string $reason, bool $cancelledByClient = true): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidLessonException('A cancellation reason is required.');
        }

        if ($this->status !== LessonStatus::Planned) {
            throw new InvalidLessonException('Only a planned lesson can be cancelled.');
        }

        $this->status = LessonStatus::Cancelled;
        $this->cancelReason = $reason;
        $this->attendance = $cancelledByClient ? Attendance::CancelledByClient : Attendance::CancelledByTeacher;
    }

    private function closeWith(Attendance $attendance, \DateTimeImmutable $now): void
    {
        if ($this->status !== LessonStatus::Planned) {
            throw new InvalidLessonException('Only a planned lesson can be closed.');
        }

        if ($now < $this->startsAt) {
            throw new InvalidLessonException('A lesson cannot be closed before it starts.');
        }

        $this->status = LessonStatus::Completed;
        $this->attendance = $attendance;
    }

    /**
     * Пересечение по времени (стык впритык не считается пересечением).
     * Проверку «тот же преподаватель» делает вызывающая сторона —
     * пересечения ищутся среди занятий одного преподавателя.
     */
    public function overlaps(self $other): bool
    {
        return $this->startsAt < $other->getEndsAt() && $other->getStartsAt() < $this->getEndsAt();
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->startsAt->add(new \DateInterval('PT' . $this->durationMinutes . 'M'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeacher(): User
    {
        return $this->teacher;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getInstrument(): ?Instrument
    {
        return $this->instrument;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function getStatus(): LessonStatus
    {
        return $this->status;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function getAttendance(): ?Attendance
    {
        return $this->attendance;
    }

    public function markReminderSent(\DateTimeImmutable $now): void
    {
        $this->reminderSentAt = $now;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    private static function assertDuration(int $durationMinutes): int
    {
        if ($durationMinutes <= 0 || $durationMinutes > self::MAX_DURATION) {
            throw new InvalidLessonException(sprintf('Duration must be between 1 and %d minutes.', self::MAX_DURATION));
        }

        return $durationMinutes;
    }

    private static function normalizeComment(?string $comment): ?string
    {
        if ($comment === null) {
            return null;
        }

        $comment = trim($comment);

        return $comment === '' ? null : $comment;
    }
}
