<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Client\Client;
use App\Domain\Event\Exception\InvalidEventException;
use App\Domain\Repertoire\RepertoirePiece;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Мероприятие школы (концерт/экзамен/конкурс) с программой номеров.
 * Порядок номеров — sortOrder с шагом 10; перестановка — обмен с соседом.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event')]
#[ORM\Index(name: 'idx_event_date', columns: ['date'])]
class Event
{
    private const int SORT_STEP = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(enumType: EventKind::class)]
    private EventKind $kind;

    #[ORM\Column]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $venue;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    /**
     * @var Collection<int, EventProgramItem>
     */
    #[ORM\OneToMany(targetEntity: EventProgramItem::class, mappedBy: 'event', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $program;

    private function __construct(string $title, EventKind $kind, \DateTimeImmutable $date, ?string $venue, ?string $description)
    {
        $this->title = self::normalizeTitle($title);
        $this->kind = $kind;
        $this->date = $date;
        $this->venue = self::normalizeOptional($venue);
        $this->description = self::normalizeOptional($description);
        $this->program = new ArrayCollection();
    }

    public static function create(string $title, EventKind $kind, \DateTimeImmutable $date, ?string $venue, ?string $description): self
    {
        return new self($title, $kind, $date, $venue, $description);
    }

    public function update(string $title, EventKind $kind, \DateTimeImmutable $date, ?string $venue, ?string $description): void
    {
        $this->title = self::normalizeTitle($title);
        $this->kind = $kind;
        $this->date = $date;
        $this->venue = self::normalizeOptional($venue);
        $this->description = self::normalizeOptional($description);
    }

    public function addProgramItem(Client $client, ?RepertoirePiece $piece, ?string $customTitle): EventProgramItem
    {
        $customTitle = self::normalizeOptional($customTitle);

        if ($piece === null && $customTitle === null) {
            throw new InvalidEventException('A program item needs a repertoire piece or a custom title.');
        }

        if ($piece !== null && $piece->getClient() !== $client
            && !($piece->getClient()->getId() !== null && $piece->getClient()->getId() === $client->getId())) {
            throw new InvalidEventException('The piece belongs to another client.');
        }

        $item = new EventProgramItem($this, $client, $piece, $piece === null ? $customTitle : null, $this->nextSortOrder());
        $this->program->add($item);

        return $item;
    }

    public function moveItem(EventProgramItem $item, bool $up): void
    {
        $ordered = $this->getProgram();
        $index = array_search($item, $ordered, true);

        if ($index === false) {
            throw new InvalidEventException('The item does not belong to this event.');
        }

        $neighbourIndex = $up ? $index - 1 : $index + 1;

        if (!isset($ordered[$neighbourIndex])) {
            return; // край программы — двигать некуда
        }

        $neighbour = $ordered[$neighbourIndex];
        $own = $item->getSortOrder();
        $item->setSortOrder($neighbour->getSortOrder());
        $neighbour->setSortOrder($own);
    }

    public function removeItem(EventProgramItem $item): void
    {
        $this->program->removeElement($item);
    }

    /**
     * Программа в порядке выступлений.
     *
     * @return list<EventProgramItem>
     */
    public function getProgram(): array
    {
        $items = $this->program->getValues();

        usort($items, static fn (EventProgramItem $a, EventProgramItem $b): int => $a->getSortOrder() <=> $b->getSortOrder());

        return $items;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getKind(): EventKind
    {
        return $this->kind;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getVenue(): ?string
    {
        return $this->venue;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    private function nextSortOrder(): int
    {
        $max = 0;

        foreach ($this->program as $item) {
            $max = max($max, $item->getSortOrder());
        }

        return $max + self::SORT_STEP;
    }

    private static function normalizeTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            throw new InvalidEventException('Event title must not be blank.');
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
