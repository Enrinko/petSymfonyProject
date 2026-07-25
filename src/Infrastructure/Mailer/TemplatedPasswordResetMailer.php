<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\PasswordReset\PasswordResetMailerInterface;
use App\Domain\User\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TemplatedPasswordResetMailer implements PasswordResetMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private EmailTemplateRenderer $renderer,
        #[Autowire(env: 'MAILER_SENDER_EMAIL')]
        private string $senderEmail,
        #[Autowire(env: 'MAILER_SENDER_NAME')]
        private string $senderName,
    ) {
    }

    public function sendResetLink(User $user, string $rawToken): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $rawToken],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        // Локаль получателя из профиля (воркер без локали запроса); фолбэк — ru
        $rendered = $this->renderer->render('password_reset', $user->getLocale() ?? 'ru', [
            'resetUrl' => $resetUrl,
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
