<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

/**
 * Готовое к отправке письмо: тема + HTML + текстовая версия.
 */
final readonly class RenderedEmail
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
    ) {
    }
}
