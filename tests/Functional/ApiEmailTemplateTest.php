<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Админ-раздел редактирования писем: список/чтение/сохранение/предпросмотр,
 * гейт ROLE_ADMIN, фолбэк на дефолты (в тест-БД нет засеянных строк).
 */
final class ApiEmailTemplateTest extends FunctionalTestCase
{
    private function loginAdmin(): void
    {
        $admin = $this->createUser(roles: ['ROLE_ADMIN']);
        $this->jsonRequest('POST', '/api/login', [
            'email' => $admin->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
    }

    public function testListReturnsAllSixTemplates(): void
    {
        $this->loginAdmin();

        $this->jsonRequest('GET', '/api/admin/emails');

        self::assertResponseIsSuccessful();
        self::assertCount(6, $this->json()['data']); // 3 письма × 2 локали
    }

    public function testReadServesDefaultWhenNotCustomized(): void
    {
        $this->loginAdmin();

        $this->jsonRequest('GET', '/api/admin/emails/password_reset/en');

        self::assertResponseIsSuccessful();
        self::assertSame('Password reset — petSymphony CRM', $this->json()['subject']);
        self::assertFalse($this->json()['customized']);
        self::assertContains('resetUrl', $this->json()['placeholders']);
    }

    public function testUpdatePersistsAndReadsBack(): void
    {
        $this->loginAdmin();

        $this->jsonRequest('PUT', '/api/admin/emails/password_reset/ru', [
            'subject' => 'Новая тема сброса',
            'bodyHtml' => '<p>Ссылка: %resetUrl%</p>',
            'bodyText' => 'Ссылка: %resetUrl%',
        ]);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/admin/emails/password_reset/ru');
        self::assertSame('Новая тема сброса', $this->json()['subject']);
        self::assertTrue($this->json()['customized']);
    }

    public function testPreviewSubstitutesPlaceholders(): void
    {
        $this->loginAdmin();

        $this->jsonRequest('POST', '/api/admin/emails/password_reset/ru/preview', [
            'subject' => 'Тест',
            'bodyHtml' => '<p>Ссылка: %resetUrl%</p>',
            'bodyText' => 'x',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('reset-password/preview-token', $this->json()['html']);
        self::assertStringNotContainsString('%resetUrl%', $this->json()['html']);
    }

    public function testValidationRejectsEmptySubject(): void
    {
        $this->loginAdmin();

        $this->jsonRequest('PUT', '/api/admin/emails/password_reset/ru', [
            'subject' => '',
            'bodyHtml' => '<p>x</p>',
            'bodyText' => 'x',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('subject', $this->json()['errors']);
    }

    public function testUnknownTemplateIs404(): void
    {
        $this->loginAdmin();

        $this->jsonRequest('GET', '/api/admin/emails/nonexistent/ru');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNonAdminIsForbidden(): void
    {
        $user = $this->createUser();
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);

        $this->jsonRequest('GET', '/api/admin/emails');

        self::assertResponseStatusCodeSame(403);
    }
}
