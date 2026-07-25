<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Domain\Email\EmailTemplateRepositoryInterface;
use Twig\Environment;

/**
 * Рендер редактируемого письма: берёт EmailTemplate(key, locale) из БД,
 * подставляет плейсхолдеры %name% (текст экранируется в HTML-контексте;
 * ключи с суффиксом _html вставляются как есть — для готовых HTML-блоков),
 * оборачивает тело в базовый каркас email/base_email.
 *
 * Сырой Twig из БД НЕ выполняется — только строковая замена (защита от SSTI).
 * Локаль-фолбэк на 'ru', если для запрошенной нет записи.
 */
final readonly class EmailTemplateRenderer
{
    private const string FALLBACK_LOCALE = 'ru';

    public function __construct(
        private EmailTemplateRepositoryInterface $templates,
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, string|int> $params
     */
    public function render(string $key, string $locale, array $params): RenderedEmail
    {
        // Из БД (редактируемое) или, если строки ещё нет (свежая установка,
        // незасеянный test/CI), из дефолтов — единый источник с командой засева
        $template = $this->templates->find($key, $locale)
            ?? $this->templates->find($key, self::FALLBACK_LOCALE);

        if ($template !== null) {
            $subjectSrc = $template->getSubject();
            $htmlSrc = $template->getBodyHtml();
            $textSrc = $template->getBodyText();
        } else {
            $default = EmailTemplateDefaults::find($key, $locale)
                ?? EmailTemplateDefaults::find($key, self::FALLBACK_LOCALE);

            if ($default === null) {
                throw new \RuntimeException(sprintf('Нет шаблона письма "%s".', $key));
            }

            $subjectSrc = $default['subject'];
            $htmlSrc = $default['html'];
            $textSrc = $default['text'];
        }

        return $this->renderFrom($subjectSrc, $htmlSrc, $textSrc, $params);
    }

    /**
     * Рендер из переданного (ещё не сохранённого) контента — для предпросмотра в админке.
     *
     * @param array<string, string|int> $params
     */
    public function preview(string $subject, string $bodyHtml, string $bodyText, array $params): RenderedEmail
    {
        return $this->renderFrom($subject, $bodyHtml, $bodyText, $params);
    }

    /**
     * @param array<string, string|int> $params
     */
    private function renderFrom(string $subjectSrc, string $htmlSrc, string $textSrc, array $params): RenderedEmail
    {
        $subject = $this->substitute($subjectSrc, $params, html: false);
        $text = $this->substitute($textSrc, $params, html: false);
        $bodyHtml = $this->substitute($htmlSrc, $params, html: true);

        $html = $this->twig->render('email/db_wrapper.html.twig', [
            'subject' => $subject,
            'body_html' => $bodyHtml,
        ]);

        return new RenderedEmail($subject, $html, $text);
    }

    /**
     * @param array<string, string|int> $params
     */
    private function substitute(string $template, array $params, bool $html): string
    {
        foreach ($params as $name => $value) {
            $raw = str_ends_with($name, '_html');
            $rendered = ($html && !$raw)
                ? htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5)
                : (string) $value;

            $template = str_replace('%' . $name . '%', $rendered, $template);
        }

        return $template;
    }
}
