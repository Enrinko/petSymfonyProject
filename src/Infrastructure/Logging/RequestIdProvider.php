<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Сквозной идентификатор запроса.
 *
 * ResetInterface обязателен: FrankenPHP работает в worker-режиме, процесс
 * живёт много запросов — services_resetter вызывает reset() между ними,
 * иначе id «протёк» бы в чужой запрос.
 */
final class RequestIdProvider implements ResetInterface
{
    private ?string $id = null;

    /** Принять id от доверенного прокси (Caddy/балансер) — с валидацией против log injection. */
    public function accept(string $inboundId): void
    {
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $inboundId) === 1) {
            $this->id = $inboundId;
        }
    }

    public function get(): string
    {
        return $this->id ??= bin2hex(random_bytes(8));
    }

    public function reset(): void
    {
        $this->id = null;
    }
}
