<?php

declare(strict_types=1);

namespace App\Domain\PasswordReset;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'password_reset_token')]
#[ORM\UniqueConstraint(name: 'uniq_password_reset_token_hash', columns: ['token_hash'])]
class PasswordResetToken
{
    private const string TTL = 'PT1H';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    private function __construct(User $user, string $tokenHash, \DateTimeImmutable $now)
    {
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->createdAt = $now;
        $this->expiresAt = $now->add(new \DateInterval(self::TTL));
    }

    public static function issueFor(User $user, string $rawToken, \DateTimeImmutable $now): self
    {
        return new self($user, self::hashOf($rawToken), $now);
    }

    public static function hashOf(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }
}
