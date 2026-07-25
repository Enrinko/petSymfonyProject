<?php

declare(strict_types=1);

namespace App\Domain\Email;

use Doctrine\ORM\Mapping as ORM;

/**
 * Редактируемый шаблон письма: одна запись на пару (ключ + локаль).
 *
 * Тело хранится как HTML/текст с плейсхолдерами вида %name% — их подставляет
 * EmailTemplateRenderer при отправке. Сырой Twig из БД НЕ выполняется (SSTI):
 * только строковая замена плейсхолдеров.
 */
#[ORM\Entity]
#[ORM\Table(name: 'email_template')]
#[ORM\UniqueConstraint(name: 'uniq_email_template_key_locale', columns: ['template_key', 'locale'])]
class EmailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Ключ письма: password_reset / verify_email / lesson_reminder. */
    #[ORM\Column(length: 64)]
    private string $templateKey;

    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $bodyHtml;

    #[ORM\Column(type: 'text')]
    private string $bodyText;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    private function __construct(string $templateKey, string $locale, string $subject, string $bodyHtml, string $bodyText)
    {
        $this->templateKey = $templateKey;
        $this->locale = $locale;
        $this->subject = $subject;
        $this->bodyHtml = $bodyHtml;
        $this->bodyText = $bodyText;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(string $templateKey, string $locale, string $subject, string $bodyHtml, string $bodyText): self
    {
        return new self($templateKey, $locale, $subject, $bodyHtml, $bodyText);
    }

    public function update(string $subject, string $bodyHtml, string $bodyText): void
    {
        $this->subject = $subject;
        $this->bodyHtml = $bodyHtml;
        $this->bodyText = $bodyText;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTemplateKey(): string
    {
        return $this->templateKey;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }

    public function getBodyText(): string
    {
        return $this->bodyText;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
