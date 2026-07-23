<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Client\Client;
use App\Domain\Repertoire\RepertoirePiece;
use Doctrine\ORM\Mapping as ORM;

/**
 * Номер программы: ученик + произведение из его репертуара,
 * либо произвольный текст (customTitle), если номера нет в репертуаре.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event_program_item')]
class EventProgramItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'program')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: RepertoirePiece::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RepertoirePiece $piece;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $customTitle;

    #[ORM\Column]
    private int $sortOrder;

    public function __construct(Event $event, Client $client, ?RepertoirePiece $piece, ?string $customTitle, int $sortOrder)
    {
        $this->event = $event;
        $this->client = $client;
        $this->piece = $piece;
        $this->customTitle = $customTitle;
        $this->sortOrder = $sortOrder;
    }

    public function displayTitle(): string
    {
        if ($this->piece !== null) {
            return $this->piece->getTitle();
        }

        return (string) $this->customTitle;
    }

    public function displayComposer(): ?string
    {
        return $this->piece?->getComposer();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getPiece(): ?RepertoirePiece
    {
        return $this->piece;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }
}
