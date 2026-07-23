<?php

declare(strict_types=1);

namespace App\Domain\Note;

use App\Domain\Client\Client;
use App\Domain\Note\Exception\InvalidNoteContentException;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Запись «дневника преподавателя» по ученику.
 * Редактирование — только автором в течение 24 часов (админ — всегда, см. NoteVoter):
 * опечатку исправить можно, «переписать историю» задним числом — нельзя.
 */
#[ORM\Entity]
#[ORM\Table(name: 'note')]
#[ORM\Index(name: 'idx_note_client_created', columns: ['client_id', 'created_at'])]
class Note
{
    private const string EDIT_WINDOW = 'PT24H';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct(Client $client, User $author, string $content, \DateTimeImmutable $now)
    {
        $this->client = $client;
        $this->author = $author;
        $this->content = self::normalizeContent($content);
        $this->createdAt = $now;
    }

    public static function create(Client $client, User $author, string $content, \DateTimeImmutable $now): self
    {
        return new self($client, $author, $content, $now);
    }

    public function updateContent(string $content, \DateTimeImmutable $now): void
    {
        $this->content = self::normalizeContent($content);
        $this->updatedAt = $now;
    }

    public function isManageableBy(User $user, \DateTimeImmutable $now): bool
    {
        $isAuthor = $this->author === $user
            || ($this->author->getId() !== null && $this->author->getId() === $user->getId());

        return $isAuthor && $now < $this->createdAt->add(new \DateInterval(self::EDIT_WINDOW));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function normalizeContent(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            throw new InvalidNoteContentException('Note content must not be blank.');
        }

        return $content;
    }
}
