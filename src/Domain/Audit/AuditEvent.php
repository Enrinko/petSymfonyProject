<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use Doctrine\ORM\Mapping as ORM;

/**
 * Запись журнала безопасности. Append-only: создаётся фабрикой,
 * сеттеров нет, приложение её не изменяет и не удаляет (кроме ретенции).
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_event')]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_audit_occurred')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_action')]
#[ORM\Index(columns: ['actor_id'], name: 'idx_audit_actor')]
class AuditEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(length: 64)]
    private string $action;

    #[ORM\Column(nullable: true)]
    private ?int $actorId;

    /** Денормализация: журнал читается без join'а и переживает смену email. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorEmail;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $subjectType;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $subjectId;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $payload;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        AuditAction $action,
        ?int $actorId,
        ?string $actorEmail,
        ?string $subjectType,
        ?string $subjectId,
        ?string $ip,
        ?string $userAgent,
        array $payload,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
        $this->action = $action->value;
        $this->actorId = $actorId;
        $this->actorEmail = $actorEmail;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->ip = $ip !== null ? mb_substr($ip, 0, 45) : null;
        $this->userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 255) : null;
        $this->payload = $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function record(
        AuditAction $action,
        ?int $actorId = null,
        ?string $actorEmail = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $ip = null,
        ?string $userAgent = null,
        array $payload = [],
    ): self {
        return new self($action, $actorId, $actorEmail, $subjectType, $subjectId, $ip, $userAgent, $payload);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
