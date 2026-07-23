<?php

declare(strict_types=1);

namespace App\Domain\Audit;

/**
 * Порт журнала безопасности. Фиксирует БИЗНЕС-действия («роль изменена»),
 * а не факты UPDATE в таблицах. Актор, IP и user agent адаптер
 * достаёт из текущего контекста сам.
 */
interface AuditLoggerInterface
{
    /**
     * @param array<string, mixed> $payload без секретов: пароли и токены — никогда
     */
    public function log(
        AuditAction $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $payload = [],
    ): void;
}
