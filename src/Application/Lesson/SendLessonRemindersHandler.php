<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Lesson\LessonRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Рассылает напоминания о занятиях, начинающихся в ближайшие N часов.
 * Идемпотентен: занятие помечается reminderSentAt и повторно не выбирается.
 * Ученики без email пропускаются БЕЗ пометки — адрес могут добавить позже,
 * и пока занятие в окне, напоминание ещё успеет уйти.
 */
final readonly class SendLessonRemindersHandler
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private LessonReminderMailerInterface $mailer,
        #[Autowire('%app.lesson_reminder_hours%')]
        private int $reminderHours,
    ) {
    }

    /** @return int сколько напоминаний отправлено */
    public function __invoke(\DateTimeImmutable $now): int
    {
        $windowEnd = $now->add(new \DateInterval('PT' . $this->reminderHours . 'H'));
        $sent = 0;

        foreach ($this->lessons->findPlannedForReminder($now, $windowEnd) as $lesson) {
            if ($lesson->getClient()->getEmail() === null) {
                continue;
            }

            $this->mailer->sendReminder($lesson);
            $lesson->markReminderSent($now);
            $this->lessons->save($lesson);
            ++$sent;
        }

        return $sent;
    }
}
