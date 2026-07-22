<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\PasswordReset\PasswordResetMailerInterface;
use App\Domain\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TemplatedPasswordResetMailer implements PasswordResetMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
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

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to($user->getEmail())
            ->subject('Сброс пароля — petSymphony CRM')
            ->htmlTemplate('email/password_reset.html.twig')
            ->textTemplate('email/password_reset.txt.twig')
            ->context(['resetUrl' => $resetUrl]);

        $this->mailer->send($email);
    }
}
