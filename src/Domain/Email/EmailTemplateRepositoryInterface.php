<?php

declare(strict_types=1);

namespace App\Domain\Email;

interface EmailTemplateRepositoryInterface
{
    public function find(string $templateKey, string $locale): ?EmailTemplate;

    /**
     * Все шаблоны, отсортированные по ключу и локали (для админ-списка).
     *
     * @return list<EmailTemplate>
     */
    public function findAll(): array;

    public function save(EmailTemplate $template): void;
}
