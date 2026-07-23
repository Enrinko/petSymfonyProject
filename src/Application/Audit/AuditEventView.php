<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\AuditEvent;

final readonly class AuditEventView
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        public int $id,
        public string $occurredAt,
        public string $action,
        public ?int $actorId,
        public ?string $actorEmail,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $ip,
        public array $payload,
    ) {
    }

    public static function fromEvent(AuditEvent $event): self
    {
        return new self(
            (int) $event->getId(),
            $event->getOccurredAt()->format(\DateTimeInterface::ATOM),
            $event->getAction(),
            $event->getActorId(),
            $event->getActorEmail(),
            $event->getSubjectType(),
            $event->getSubjectId(),
            $event->getIp(),
            $event->getPayload(),
        );
    }
}
