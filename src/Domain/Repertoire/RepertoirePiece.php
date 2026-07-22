<?php

declare(strict_types=1);

namespace App\Domain\Repertoire;

use App\Domain\Client\Client;
use App\Domain\Repertoire\Exception\InvalidPieceException;
use Doctrine\ORM\Mapping as ORM;

/**
 * Произведение в работе у ученика. Записи индивидуальны:
 * даже одна и та же пьеса у двух учеников — разные статусы и заметки.
 */
#[ORM\Entity]
#[ORM\Table(name: 'repertoire_piece')]
#[ORM\Index(name: 'idx_piece_client_status', columns: ['client_id', 'status'])]
class RepertoirePiece
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $composer;

    #[ORM\Column(enumType: PieceStatus::class)]
    private PieceStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    private function __construct(Client $client, string $title, ?string $composer, \DateTimeImmutable $startedAt)
    {
        $this->client = $client;
        $this->title = self::normalizeTitle($title);
        $this->composer = self::normalizeOptional($composer);
        $this->status = PieceStatus::Learning;
        $this->startedAt = $startedAt;
    }

    public static function add(Client $client, string $title, ?string $composer, \DateTimeImmutable $startedAt): self
    {
        return new self($client, $title, $composer, $startedAt);
    }

    public function advance(): void
    {
        $next = $this->status->next()
            ?? throw new InvalidPieceException('The piece is already in the repertoire.');

        $this->status = $next;
    }

    public function stepBack(): void
    {
        $previous = $this->status->previous()
            ?? throw new InvalidPieceException('The piece is already at the initial status.');

        $this->status = $previous;
    }

    public function updateNote(?string $note): void
    {
        $this->note = self::normalizeOptional($note);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getComposer(): ?string
    {
        return $this->composer;
    }

    public function getStatus(): PieceStatus
    {
        return $this->status;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    private static function normalizeTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            throw new InvalidPieceException('Piece title must not be blank.');
        }

        return $title;
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
