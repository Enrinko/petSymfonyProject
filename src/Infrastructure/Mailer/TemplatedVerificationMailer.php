<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\User\VerificationMailerInterface;
use App\Domain\User\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Ссылка подтверждения: подпись + TTL от verify-email-bundle,
 * письмо уходит асинхронно через messenger (как и остальная почта).
 */
final readonly class TemplatedVerificationMailer implements VerificationMailerInterface
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
    ) {
    }

    public function sendVerificationLink(User $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            // id обязан попасть в query сам: по ссылке приходят без сессии,
            // и контроллеру больше неоткуда узнать, кого верифицировать
            ['id' => (string) $user->getId()],
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@petsymphony.local', 'petSymphony'))
            ->to($user->getEmail())
            ->subject('Подтвердите email — petSymphony')
            ->htmlTemplate('email/verify_email.html.twig')
            ->context([
                'verifyUrl' => $signature->getSignedUrl(),
                'expiresAtMessageKey' => $signature->getExpirationMessageKey(),
                'expiresAtMessageData' => $signature->getExpirationMessageData(),
            ]);

        $this->mailer->send($email);
    }
}
