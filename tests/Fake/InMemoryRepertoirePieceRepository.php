<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Client\Client;
use App\Domain\Repertoire\RepertoirePiece;
use App\Domain\Repertoire\RepertoirePieceRepositoryInterface;

final class InMemoryRepertoirePieceRepository implements RepertoirePieceRepositoryInterface
{
    /**
     * @var array<int, RepertoirePiece>
     */
    private array $byId = [];

    /**
     * @var list<RepertoirePiece>
     */
    public array $saved = [];

    /**
     * @var list<RepertoirePiece>
     */
    public array $removed = [];

    public function withPiece(int $id, RepertoirePiece $piece): self
    {
        new \ReflectionProperty(RepertoirePiece::class, 'id')->setValue($piece, $id);
        $this->byId[$id] = $piece;

        return $this;
    }

    public function find(int $id): ?RepertoirePiece
    {
        return $this->byId[$id] ?? null;
    }

    public function findByClient(Client $client): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (RepertoirePiece $piece): bool => $piece->getClient() === $client,
        ));
    }

    public function save(RepertoirePiece $piece): void
    {
        $this->saved[] = $piece;

        if ($piece->getId() !== null) {
            $this->byId[$piece->getId()] = $piece;
        }
    }

    public function remove(RepertoirePiece $piece): void
    {
        $this->removed[] = $piece;

        if ($piece->getId() !== null) {
            unset($this->byId[$piece->getId()]);
        }
    }
}
