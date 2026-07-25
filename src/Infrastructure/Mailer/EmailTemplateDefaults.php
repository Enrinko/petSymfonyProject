<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

/**
 * Дефолтный контент писем (RU/EN). Единый источник для команды засева
 * (app:emails:seed) и для фолбэка рендера, если в БД ещё нет строки
 * (свежая установка, незасеянный test/CI). Плейсхолдеры — %name%.
 */
final class EmailTemplateDefaults
{
    /**
     * @return array{subject: string, html: string, text: string}|null
     */
    public static function find(string $key, string $locale): ?array
    {
        return self::all()[$key][$locale] ?? null;
    }

    /**
     * @return array<string, array<string, array{subject: string, html: string, text: string}>>
     */
    public static function all(): array
    {
        $button = static fn (string $url, string $label): string => <<<HTML
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                <tr><td style="border-radius:10px;background:#9a6b15;">
                    <a href="{$url}" style="display:inline-block;padding:13px 28px;font-size:15px;font-weight:600;color:#fffdf9;text-decoration:none;border-radius:10px;">{$label}</a>
                </td></tr>
            </table>
            HTML;

        $h1 = 'margin:0 0 16px;font-size:22px;color:#23253a;';
        $p = 'margin:0 0 12px;font-size:15px;line-height:1.6;color:#6f7288;';
        $small = 'margin:0 0 8px;font-size:13px;line-height:1.6;color:#a0a3b8;';
        $link = 'margin:0 0 16px;font-size:13px;line-height:1.6;word-break:break-all;';
        $note = 'margin:0;font-size:12px;line-height:1.6;color:#a0a3b8;';

        return [
            'password_reset' => [
                'ru' => [
                    'subject' => 'Сброс пароля — petSymphony CRM',
                    'html' => "<h1 style=\"{$h1}\">Сброс пароля</h1>\n"
                        . "<p style=\"{$p}\">Мы получили запрос на сброс пароля для вашего аккаунта. Нажмите на кнопку ниже, чтобы задать новый пароль. Ссылка действует <strong>1&nbsp;час</strong>.</p>\n"
                        . $button('%resetUrl%', 'Задать новый пароль') . "\n"
                        . "<p style=\"{$small}\">Если кнопка не работает, скопируйте ссылку в адресную строку браузера:</p>\n"
                        . "<p style=\"{$link}\"><a href=\"%resetUrl%\" style=\"color:#9a6b15;\">%resetUrl%</a></p>\n"
                        . "<p style=\"{$note}\">Если вы не запрашивали сброс пароля — просто проигнорируйте это письмо, ваш пароль останется прежним.</p>",
                    'text' => "Сброс пароля — petSymphony CRM\n\nМы получили запрос на сброс пароля. Откройте ссылку, чтобы задать новый пароль (действует 1 час):\n%resetUrl%\n\nЕсли вы не запрашивали сброс — просто проигнорируйте это письмо.",
                ],
                'en' => [
                    'subject' => 'Password reset — petSymphony CRM',
                    'html' => "<h1 style=\"{$h1}\">Password reset</h1>\n"
                        . "<p style=\"{$p}\">We received a request to reset the password for your account. Click the button below to set a new password. The link is valid for <strong>1&nbsp;hour</strong>.</p>\n"
                        . $button('%resetUrl%', 'Set a new password') . "\n"
                        . "<p style=\"{$small}\">If the button does not work, copy the link into your browser's address bar:</p>\n"
                        . "<p style=\"{$link}\"><a href=\"%resetUrl%\" style=\"color:#9a6b15;\">%resetUrl%</a></p>\n"
                        . "<p style=\"{$note}\">If you did not request a password reset, just ignore this email — your password will stay the same.</p>",
                    'text' => "Password reset — petSymphony CRM\n\nWe received a request to reset your password. Open the link to set a new password (valid for 1 hour):\n%resetUrl%\n\nIf you did not request a reset, just ignore this email.",
                ],
            ],
            'verify_email' => [
                'ru' => [
                    'subject' => 'Подтвердите email — petSymphony',
                    'html' => "<h1 style=\"{$h1}\">Добро пожаловать в оркестр!</h1>\n"
                        . "<p style=\"{$p}\">Остался один шаг: подтвердите, что этот email принадлежит вам. Ссылка действует <strong>1&nbsp;час</strong>.</p>\n"
                        . $button('%verifyUrl%', 'Подтвердить email') . "\n"
                        . "<p style=\"{$small}\">Если кнопка не работает, скопируйте ссылку в адресную строку браузера:</p>\n"
                        . "<p style=\"{$link}\"><a href=\"%verifyUrl%\" style=\"color:#9a6b15;\">%verifyUrl%</a></p>\n"
                        . "<p style=\"{$note}\">Если вы не регистрировались в petSymphony — просто проигнорируйте это письмо.</p>",
                    'text' => "Подтвердите email — petSymphony\n\nОстался один шаг: подтвердите, что этот email принадлежит вам (ссылка действует 1 час):\n%verifyUrl%\n\nЕсли вы не регистрировались — проигнорируйте это письмо.",
                ],
                'en' => [
                    'subject' => 'Confirm your email — petSymphony',
                    'html' => "<h1 style=\"{$h1}\">Welcome to the orchestra!</h1>\n"
                        . "<p style=\"{$p}\">One step left: confirm that this email belongs to you. The link is valid for <strong>1&nbsp;hour</strong>.</p>\n"
                        . $button('%verifyUrl%', 'Confirm email') . "\n"
                        . "<p style=\"{$small}\">If the button does not work, copy the link into your browser's address bar:</p>\n"
                        . "<p style=\"{$link}\"><a href=\"%verifyUrl%\" style=\"color:#9a6b15;\">%verifyUrl%</a></p>\n"
                        . "<p style=\"{$note}\">If you did not sign up for petSymphony, just ignore this email.</p>",
                    'text' => "Confirm your email — petSymphony\n\nOne step left: confirm that this email belongs to you (link valid for 1 hour):\n%verifyUrl%\n\nIf you did not sign up, just ignore this email.",
                ],
            ],
            'lesson_reminder' => [
                'ru' => [
                    'subject' => 'Напоминание о занятии %datetime% — petSymphony',
                    'html' => "<h1 style=\"{$h1}\">Напоминание о занятии</h1>\n"
                        . "<p style=\"{$p}\">Здравствуйте, %clientName%!</p>\n"
                        . "<p style=\"margin:0 0 12px;font-size:15px;line-height:1.6;color:#23253a;\">Напоминаем: <strong>%date% в %time%</strong> у вас занятие%instrument% (%duration% мин).</p>\n"
                        . "%comment_block_html%\n"
                        . "<p style=\"{$note}\">Если планы поменялись — предупредите преподавателя заранее.</p>",
                    'text' => "Напоминание о занятии — petSymphony\n\nЗдравствуйте, %clientName%!\nНапоминаем: %date% в %time% у вас занятие%instrument% (%duration% мин).\n\nЕсли планы поменялись — предупредите преподавателя заранее.",
                ],
                'en' => [
                    'subject' => 'Lesson reminder %datetime% — petSymphony',
                    'html' => "<h1 style=\"{$h1}\">Lesson reminder</h1>\n"
                        . "<p style=\"{$p}\">Hello, %clientName%!</p>\n"
                        . "<p style=\"margin:0 0 12px;font-size:15px;line-height:1.6;color:#23253a;\">A reminder: on <strong>%date% at %time%</strong> you have a lesson%instrument% (%duration% min).</p>\n"
                        . "%comment_block_html%\n"
                        . "<p style=\"{$note}\">If your plans have changed, please let your teacher know in advance.</p>",
                    'text' => "Lesson reminder — petSymphony\n\nHello, %clientName%!\nA reminder: on %date% at %time% you have a lesson%instrument% (%duration% min).\n\nIf your plans have changed, please let your teacher know in advance.",
                ],
            ],
        ];
    }
}
