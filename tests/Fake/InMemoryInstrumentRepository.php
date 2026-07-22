<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentRepositoryInterface;

final class InMemoryInstrumentRepository implements InstrumentRepositoryInterface
{
    /**
     * @var array<int, Instrument>
     */
    private array $byId = [];

    /**
     * @var list<Instrument>
     */
    public array $saved = [];

    /**
     * @var list<Instrument>
     */
    public array $removed = [];

    public function withInstrument(int $id, Instrument $instrument): self
    {
        // Эмулируем Doctrine: проставляем identity, чтобы getId() совпадал с ключом
        new \ReflectionProperty(Instrument::class, 'id')->setValue($instrument, $id);
        $this->byId[$id] = $instrument;

        return $this;
    }

    public function find(int $id): ?Instrument
    {
        return $this->byId[$id] ?? null;
    }

    public function findByIds(array $ids): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Instrument $instrument): bool => \in_array($instrument->getId(), $ids, true),
        ));
    }

    public function findByName(string $name): ?Instrument
    {
        foreach ($this->byId as $instrument) {
            if (mb_strtolower($instrument->getName()) === mb_strtolower(trim($name))) {
                return $instrument;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_values($this->byId);
    }

    public function save(Instrument $instrument): void
    {
        $this->saved[] = $instrument;
    }

    public function remove(Instrument $instrument): void
    {
        $this->removed[] = $instrument;
    }
}
