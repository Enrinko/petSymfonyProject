<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Lesson\LessonReminderMailerInterface;
use App\Domain\Lesson\Lesson;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class TemplatedLessonReminderMailer implements LessonReminderMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(env: 'MAILER_SENDER_EMAIL')]
        private string $senderEmail,
        #[Autowire(env: 'MAILER_SENDER_NAME')]
        private string $senderName,
    ) {
    }

    public function sendReminder(Lesson $lesson): void
    {
        $clientEmail = $lesson->getClient()->getEmail();
        \assert($clientEmail !== null, 'Хендлер пропускает учеников без email');

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to($clientEmail)
            ->subject(sprintf('Напоминание о занятии %s — petSymphony', $lesson->getStartsAt()->format('d.m в H:i')))
            ->htmlTemplate('email/lesson_reminder.html.twig')
            ->textTemplate('email/lesson_reminder.txt.twig')
            ->context(['lesson' => $lesson]);

        $this->mailer->send($email);
    }
}
