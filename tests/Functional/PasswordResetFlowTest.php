<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Сквозной флоу сброса пароля: запрос → письмо (in-memory транспорт Messenger)
 * → переход по токену → вход новым паролем. Плюс защита от перечисления email.
 */
final class PasswordResetFlowTest extends FunctionalTestCase
{
    public function testFullResetFlow(): void
    {
        $user = $this->createUser();
        $newPassword = 'N3w!Passw0rd#Later';

        $this->jsonRequest('POST', '/api/password/forgot', ['email' => $user->getEmail()]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $token = $this->extractTokenFromQueuedEmail();

        $this->jsonRequest('POST', '/api/password/reset', [
            'token' => $token,
            'password' => $newPassword,
            'passwordConfirm' => $newPassword,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        // Старый пароль больше не работает…
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());

        // …а новый — работает
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => $newPassword,
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testUnknownEmailGetsSameNeutralAnswerAndNoEmail(): void
    {
        $this->jsonRequest('POST', '/api/password/forgot', [
            'email' => sprintf('nobody-%s@example.test', uniqid()),
        ]);

        // Тот же нейтральный 200, что и для существующего адреса — без перечисления
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $this->mailTransport()->getSent());
    }

    public function testUsedTokenCannotBeReplayed(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/password/forgot', ['email' => $user->getEmail()]);
        $token = $this->extractTokenFromQueuedEmail();

        $this->jsonRequest('POST', '/api/password/reset', [
            'token' => $token,
            'password' => 'N3w!Passw0rd#Later', 'passwordConfirm' => 'N3w!Passw0rd#Later',
        ]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->jsonRequest('POST', '/api/password/reset', [
            'token' => $token,
            'password' => 'An0ther!Passw0rd#X', 'passwordConfirm' => 'An0ther!Passw0rd#X',
        ]);
        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
    }

    private function mailTransport(): InMemoryTransport
    {
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function extractTokenFromQueuedEmail(): string
    {
        $sent = $this->mailTransport()->getSent();
        self::assertNotEmpty($sent, 'Письмо не попало в очередь.');

        $message = end($sent)->getMessage();
        self::assertInstanceOf(SendEmailMessage::class, $message);

        $email = $message->getMessage();
        self::assertInstanceOf(Email::class, $email);

        // Письмо рендерится из БД-шаблона: ссылка — прямо в HTML-теле
        $html = (string) $email->getHtmlBody();
        self::assertSame(1, preg_match('#/reset-password/([^/?\s"<]+)#', $html, $m), $html);

        return $m[1];
    }
}
