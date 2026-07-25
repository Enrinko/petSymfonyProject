<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Lesson\LessonReminderMailerInterface;
use App\Domain\Lesson\Lesson;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class TemplatedLessonReminderMailer implements LessonReminderMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailTemplateRenderer $renderer,
        #[Autowire(env: 'MAILER_SENDER_EMAIL')]
        private string $senderEmail,
        #[Autowire(env: 'MAILER_SENDER_NAME')]
        private string $senderName,
    ) {
    }

    public function sendReminder(Lesson $lesson): void
    {
        $client = $lesson->getClient();
        $clientEmail = $client->getEmail();
        \assert($clientEmail !== null, 'Хендлер пропускает учеников без email');

        // Локаль письма — преподавателя (владельца ученика); воркер без локали запроса
        $locale = $client->getOwner()->getLocale() ?? 'ru';
        $isEn = $locale === 'en';
        $startsAt = $lesson->getStartsAt();

        $instrument = $lesson->getInstrument();
        $instrumentSuffix = $instrument !== null ? ' — ' . mb_strtolower($instrument->getName()) : '';

        $commentBlockHtml = '';
        $comment = $lesson->getComment();

        if ($comment !== null && $comment !== '') {
            $label = $isEn ? "Teacher's note" : 'Комментарий преподавателя';
            $commentBlockHtml = sprintf(
                '<p style="margin:0 0 4px;font-size:14px;line-height:1.6;color:#6f7288;border-left:3px solid #c8902c;padding-left:12px;">%s: %s</p>',
                $label,
                htmlspecialchars($comment, ENT_QUOTES | ENT_HTML5),
            );
        }

        $rendered = $this->renderer->render('lesson_reminder', $locale, [
            'datetime' => $startsAt->format($isEn ? 'M j, H:i' : 'd.m в H:i'),
            'clientName' => $client->getName(),
            'date' => $startsAt->format($isEn ? 'M j, Y' : 'd.m.Y'),
            'time' => $startsAt->format('H:i'),
            'instrument' => $instrumentSuffix,
            'duration' => (string) $lesson->getDurationMinutes(),
            'comment_block_html' => $commentBlockHtml,
        ]);

        $this->mailer->send(
            (new Email())
                ->from(new Address($this->senderEmail, $this->senderName))
                ->to($clientEmail)
                ->subject($rendered->subject)
                ->html($rendered->html)
                ->text($rendered->text),
        );
    }
}
