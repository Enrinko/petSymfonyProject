<?php

declare(strict_types=1);

namespace App\Domain\Instrument;

interface InstrumentRepositoryInterface
{
    public function find(int $id): ?Instrument;

    /**
     * @param list<int> $ids
     *
     * @return list<Instrument>
     */
    public function findByIds(array $ids): array;

    public function findByName(string $name): ?Instrument;

    /**
     * Весь справочник, упорядоченный (sortOrder, name).
     *
     * @return list<Instrument>
     */
    public function findAll(): array;

    public function save(Instrument $instrument): void;

    public function remove(Instrument $instrument): void;
}
