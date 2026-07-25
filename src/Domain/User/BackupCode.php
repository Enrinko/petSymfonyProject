<?php

declare(strict_types=1);

namespace App\Domain\User;

use Doctrine\ORM\Mapping as ORM;

/**
 * Резервный код 2FA. Хранится только bcrypt-хэшем; одноразовый —
 * использованный помечается usedAt (не удаляется: след для аудита).
 */
#[ORM\Entity]
#[ORM\Table(name: 'backup_code')]
#[ORM\Index(columns: ['user_id'], name: 'idx_backup_code_user')]
class BackupCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $codeHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(User $user, string $codeHash)
    {
        $this->user = $user;
        $this->codeHash = $codeHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function matches(string $plainCode): bool
    {
        return $this->usedAt === null && password_verify($plainCode, $this->codeHash);
    }

    public function markUsed(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }
}
