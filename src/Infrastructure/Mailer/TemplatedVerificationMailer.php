<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\User\VerificationMailerInterface;
use App\Domain\User\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Ссылка подтверждения: подпись + TTL от verify-email-bundle,
 * письмо уходит асинхронно через messenger (как и остальная почта).
 * Контент — редактируемый шаблон из БД (EmailTemplateRenderer).
 */
final readonly class TemplatedVerificationMailer implements VerificationMailerInterface
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EmailTemplateRenderer $renderer,
        #[Autowire(env: 'MAILER_SENDER_EMAIL')]
        private string $senderEmail,
        #[Autowire(env: 'MAILER_SENDER_NAME')]
        private string $senderName,
    ) {
    }

    public function sendVerificationLink(User $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => (string) $user->getId()],
        );

        $rendered = $this->renderer->render('verify_email', $user->getLocale() ?? 'ru', [
            'verifyUrl' => $signature->getSignedUrl(),
        ]);

        $this->mailer->send(
            (new Email())
                ->from(new Address($this->senderEmail, $this->senderName))
                ->to($user->getEmail())
                ->subject($rendered->subject)
                ->html($rendered->html)
                ->text($rendered->text),
        );
    }
}
