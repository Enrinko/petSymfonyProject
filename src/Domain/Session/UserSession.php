<?php

declare(strict_types=1);

namespace App\Domain\Session;

use Doctrine\ORM\Mapping as ORM;

/**
 * Учётная запись активной сессии пользователя.
 *
 * Хранится ХЭШ идентификатора сессии: по утёкшей таблице нельзя
 * восстановить cookie и угнать сессию.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_session')]
#[ORM\UniqueConstraint(name: 'uniq_session_hash', columns: ['session_id_hash'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_session_user')]
class UserSession
{
    /** Троттлинг записи lastSeenAt: не чаще раза в минуту. */
    private const int TOUCH_INTERVAL_SECONDS = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $sessionIdHash;

    #[ORM\Column]
    private int $userId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    private function __construct(string $sessionIdHash, int $userId, ?string $userAgent, ?string $ip)
    {
        $this->sessionIdHash = $sessionIdHash;
        $this->userId = $userId;
        $this->userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 255) : null;
        $this->ip = $ip !== null ? mb_substr($ip, 0, 45) : null;
        $this->createdAt = new \DateTimeImmutable();
        $this->lastSeenAt = $this->createdAt;
    }

    public static function open(string $sessionIdHash, int $userId, ?string $userAgent, ?string $ip): self
    {
        return new self($sessionIdHash, $userId, $userAgent, $ip);
    }

    public static function hashOf(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    /** @return bool true, если lastSeenAt реально обновлён (пора сохранять) */
    public function touch(\DateTimeImmutable $now): bool
    {
        if ($now->getTimestamp() - $this->lastSeenAt->getTimestamp() < self::TOUCH_INTERVAL_SECONDS) {
            return false;
        }

        $this->lastSeenAt = $now;

        return true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionIdHash(): string
    {
        return $this->sessionIdHash;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }
}
