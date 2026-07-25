<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Controller\Api\ApiJson;
use App\Domain\Email\EmailTemplate;
use App\Domain\Email\EmailTemplateRepositoryInterface;
use App\Infrastructure\Mailer\EmailTemplateDefaults;
use App\Infrastructure\Mailer\EmailTemplateRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Раздел редактирования писем (админ). Контент хранится в email_template;
 * список/форма всегда показывают все письма (БД-переопределение или дефолт).
 */
#[Route('/api/admin/emails')]
#[IsGranted('ROLE_ADMIN')]
final class EmailTemplateController extends AbstractController
{
    /** Плейсхолдеры, доступные в каждом письме (для подсказки в редакторе). */
    private const array PLACEHOLDERS = [
        'password_reset' => ['resetUrl'],
        'verify_email' => ['verifyUrl'],
        'lesson_reminder' => ['datetime', 'clientName', 'date', 'time', 'instrument', 'duration', 'comment_block_html'],
    ];

    /** Пример значений для предпросмотра. */
    private const array PREVIEW_PARAMS = [
        'password_reset' => ['resetUrl' => 'https://petsymphony.localhost/reset-password/preview-token'],
        'verify_email' => ['verifyUrl' => 'https://petsymphony.localhost/verify-email?token=preview'],
        'lesson_reminder' => [
            'datetime' => '20.05 в 18:00',
            'clientName' => 'Анна Скрипкина',
            'date' => '20.05.2026',
            'time' => '18:00',
            'instrument' => ' — фортепиано',
            'duration' => '45',
            'comment_block_html' => '<p style="margin:0 0 4px;font-size:14px;line-height:1.6;color:#6f7288;border-left:3px solid #c8902c;padding-left:12px;">Комментарий преподавателя: не забудьте ноты.</p>',
        ],
    ];

    #[Route('', name: 'api_admin_emails_list', methods: ['GET'])]
    public function list(EmailTemplateRepositoryInterface $templates): JsonResponse
    {
        $data = [];

        foreach (EmailTemplateDefaults::all() as $key => $locales) {
            foreach ($locales as $locale => $default) {
                $row = $templates->find($key, $locale);

                $data[] = [
                    'key' => $key,
                    'locale' => $locale,
                    'subject' => $row?->getSubject() ?? $default['subject'],
                    'customized' => $row !== null,
                    'updatedAt' => $row?->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                ];
            }
        }

        return $this->json(['data' => $data]);
    }

    #[Route('/{key}/{locale}', name: 'api_admin_emails_show', methods: ['GET'], requirements: ['locale' => 'ru|en'])]
    public function show(string $key, string $locale, EmailTemplateRepositoryInterface $templates): JsonResponse
    {
        $default = EmailTemplateDefaults::find($key, $locale);

        if ($default === null) {
            return ApiJson::error('api.email.unknown', Response::HTTP_NOT_FOUND);
        }

        $row = $templates->find($key, $locale);

        return $this->json([
            'key' => $key,
            'locale' => $locale,
            'subject' => $row?->getSubject() ?? $default['subject'],
            'bodyHtml' => $row?->getBodyHtml() ?? $default['html'],
            'bodyText' => $row?->getBodyText() ?? $default['text'],
            'placeholders' => self::PLACEHOLDERS[$key] ?? [],
            'customized' => $row !== null,
        ]);
    }

    #[Route('/{key}/{locale}', name: 'api_admin_emails_update', methods: ['PUT'], requirements: ['locale' => 'ru|en'])]
    public function update(string $key, string $locale, Request $request, EmailTemplateRepositoryInterface $templates): JsonResponse
    {
        if (EmailTemplateDefaults::find($key, $locale) === null) {
            return ApiJson::error('api.email.unknown', Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $subject = trim((string) ($payload['subject'] ?? ''));
        $bodyHtml = (string) ($payload['bodyHtml'] ?? '');
        $bodyText = (string) ($payload['bodyText'] ?? '');

        $errors = [];

        if ($subject === '' || mb_strlen($subject) > 255) {
            $errors['subject'] = 'Тема обязательна и не длиннее 255 символов.';
        }

        if (trim($bodyHtml) === '') {
            $errors['bodyHtml'] = 'HTML-тело обязательно.';
        }

        if (trim($bodyText) === '') {
            $errors['bodyText'] = 'Текстовое тело обязательно.';
        }

        if ($errors !== []) {
            return ApiJson::error('api.validation_failed', Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
        }

        $row = $templates->find($key, $locale);

        if ($row !== null) {
            $row->update($subject, $bodyHtml, $bodyText);
        } else {
            $row = EmailTemplate::create($key, $locale, $subject, $bodyHtml, $bodyText);
        }

        $templates->save($row);

        return $this->json(['status' => 'saved', 'updatedAt' => $row->getUpdatedAt()->format(\DateTimeInterface::ATOM)]);
    }

    #[Route('/{key}/{locale}/preview', name: 'api_admin_emails_preview', methods: ['POST'], requirements: ['locale' => 'ru|en'])]
    public function preview(string $key, string $locale, Request $request, EmailTemplateRenderer $renderer): JsonResponse
    {
        if (!isset(self::PREVIEW_PARAMS[$key])) {
            return ApiJson::error('api.email.unknown', Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $rendered = $renderer->preview(
            trim((string) ($payload['subject'] ?? '')),
            (string) ($payload['bodyHtml'] ?? ''),
            (string) ($payload['bodyText'] ?? ''),
            self::PREVIEW_PARAMS[$key],
        );

        return $this->json(['subject' => $rendered->subject, 'html' => $rendered->html]);
    }
}
