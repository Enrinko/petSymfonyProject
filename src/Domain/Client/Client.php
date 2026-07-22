<?php

declare(strict_types=1);

namespace App\Domain\Client;

use App\Domain\Client\Exception\InvalidClientNameException;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'client')]
#[ORM\Index(name: 'idx_client_archived_at', columns: ['archived_at'])]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment;

    /**
     * Кто ведёт клиента. Заводим сразу: добавлять владельца задним числом больно.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    private function __construct(
        string $name,
        User $owner,
        \DateTimeImmutable $now,
        ?string $email,
        ?string $phone,
        ?string $comment,
    ) {
        $this->name = self::normalizeName($name);
        $this->owner = $owner;
        $this->createdAt = $now;
        $this->email = self::normalizeOptional($email);
        $this->phone = self::normalizeOptional($phone);
        $this->comment = self::normalizeOptional($comment);
    }

    public static function create(
        string $name,
        User $owner,
        \DateTimeImmutable $now,
        ?string $email = null,
        ?string $phone = null,
        ?string $comment = null,
    ): self {
        return new self($name, $owner, $now, $email, $phone, $comment);
    }

    public function update(
        string $name,
        \DateTimeImmutable $now,
        ?string $email = null,
        ?string $phone = null,
        ?string $comment = null,
    ): void {
        $this->name = self::normalizeName($name);
        $this->email = self::normalizeOptional($email);
        $this->phone = self::normalizeOptional($phone);
        $this->comment = self::normalizeOptional($comment);
        $this->updatedAt = $now;
    }

    public function archive(\DateTimeImmutable $now): void
    {
        // Идемпотентно: повторный архив не двигает исходную дату.
        $this->archivedAt ??= $now;
    }

    public function restore(): void
    {
        $this->archivedAt = null;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidClientNameException('Client name must not be blank.');
        }

        return $name;
    }

    private static function normalizeOptional(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
