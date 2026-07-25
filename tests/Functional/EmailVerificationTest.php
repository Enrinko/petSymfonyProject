<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Подтверждение email: письмо при регистрации, переход по подписанной
 * ссылке, идемпотентность, отказ по битой подписи, resend с лимитом,
 * плашка в интерфейсе.
 */
final class EmailVerificationTest extends FunctionalTestCase
{
    public function testRegistrationSendsVerificationEmailAndLinkVerifies(): void
    {
        $email = sprintf('verify-%s@example.test', uniqid());

        $this->jsonRequest('POST', '/api/register', [
            'email' => $email,
            'password' => self::STRONG_PASSWORD,
            'passwordConfirm' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        $verifyUrl = $this->extractVerifyUrlFromQueuedEmail();
        self::assertStringContainsString('/verify-email', $verifyUrl);

        // Свежий юзер не подтверждён
        $user = $this->findUser($email);
        self::assertFalse($user->isVerified());

        // Переход по ссылке (аноним — письмо открыто без сессии)
        $this->client->request('GET', $verifyUrl);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('подтверждён', (string) $this->client->getResponse()->getContent());

        // Kernel ребутается между запросами — перечитываем юзера свежим EM
        $user = $this->findUser($email);
        self::assertTrue($user->isVerified());

        // Повторный переход — идемпотентно, тот же успех
        // (сравнение по секундам: БД хранит TIMESTAMP(0), identity map — микросекунды)
        $firstVerifiedAt = $user->getVerifiedAt()?->format('Y-m-d H:i:s');
        $this->client->request('GET', $verifyUrl);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame($firstVerifiedAt, $this->findUser($email)->getVerifiedAt()?->format('Y-m-d H:i:s'));
    }

    public function testTamperedSignatureIsRejected(): void
    {
        $email = sprintf('tamper-%s@example.test', uniqid());
        $this->jsonRequest('POST', '/api/register', [
            'email' => $email,
            'password' => self::STRONG_PASSWORD,
            'passwordConfirm' => self::STRONG_PASSWORD,
        ]);

        $verifyUrl = $this->extractVerifyUrlFromQueuedEmail();

        // Ломаем подпись
        $this->client->request('GET', str_replace('signature=', 'signature=broken', $verifyUrl));

        self::assertStringContainsString('Не получилось', (string) $this->client->getResponse()->getContent());
        self::assertFalse($this->findUser($email)->isVerified());
    }

    public function testUnverifiedUserSeesBannerVerifiedDoesNot(): void
    {
        // createUser создаёт неподтверждённого
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/');
        self::assertStringContainsString('verify-banner', (string) $this->client->getResponse()->getContent());

        $user->markVerified();
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->request('GET', '/');
        self::assertStringNotContainsString('verify-banner', (string) $this->client->getResponse()->getContent());
    }

    public function testResendSendsEmailAndIsRateLimited(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        for ($i = 1; $i <= 3; ++$i) {
            $this->jsonRequest('POST', '/api/verify-email/resend');
            self::assertSame(200, $this->client->getResponse()->getStatusCode(), sprintf('Отправка %d.', $i));
            // Kernel ребутается между запросами — in-memory транспорт видит
            // только письмо текущего запроса
            self::assertCount(1, $this->mailTransport()->getSent(), sprintf('Письмо %d.', $i));
        }

        // Счётчик лимитера живёт в файловом пуле и ребут переживает
        $this->jsonRequest('POST', '/api/verify-email/resend');
        self::assertSame(429, $this->client->getResponse()->getStatusCode());
    }

    public function testResendForVerifiedUserSendsNothing(): void
    {
        $user = $this->createUser();
        $user->markVerified();
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->client->loginUser($user);

        $this->jsonRequest('POST', '/api/verify-email/resend');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $this->mailTransport()->getSent());
    }

    private function findUser(string $email): User
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)->findOneBy(['email' => $email]);
        \assert($user instanceof User);

        return $user;
    }

    private function mailTransport(): InMemoryTransport
    {
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function extractVerifyUrlFromQueuedEmail(): string
    {
        $sent = $this->mailTransport()->getSent();
        self::assertNotEmpty($sent, 'Письмо не попало в очередь.');

        $message = end($sent)->getMessage();
        self::assertInstanceOf(SendEmailMessage::class, $message);

        $email = $message->getMessage();
        self::assertInstanceOf(Email::class, $email);

        // Ссылка подтверждения — в HTML-теле; в HTML-контексте & экранирован в &amp;
        $html = (string) $email->getHtmlBody();
        self::assertSame(1, preg_match('#href="([^"]*verify-email[^"]+)"#', $html, $m), $html);

        return html_entity_decode($m[1]);
    }
}
