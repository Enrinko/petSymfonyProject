<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;

final class SpyAuditLogger implements AuditLoggerInterface
{
    /**
     * @var list<array{action: AuditAction, subjectType: ?string, subjectId: ?string, payload: array<string, mixed>}>
     */
    public array $entries = [];

    public function log(
        AuditAction $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $payload = [],
    ): void {
        $this->entries[] = [
            'action' => $action,
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
            'payload' => $payload,
        ];
    }

    public function lastAction(): ?AuditAction
    {
        return $this->entries === [] ? null : $this->entries[array_key_last($this->entries)]['action'];
    }
}
