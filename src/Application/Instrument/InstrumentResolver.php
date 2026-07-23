<?php

declare(strict_types=1);

namespace App\Application\Instrument;

use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentRepositoryInterface;

/**
 * id из формы → сущности справочника. В отличие от тегов, на лету
 * инструменты НЕ создаются: справочник — источник истины, неизвестные id
 * молча отбрасываются. Порядок входных id сохраняется.
 */
final readonly class InstrumentResolver
{
    public function __construct(
        private InstrumentRepositoryInterface $instruments,
    ) {
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Instrument>
     */
    public function resolve(array $ids): array
    {
        $unique = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        if ($unique === []) {
            return [];
        }

        $byId = [];

        foreach ($this->instruments->findByIds($unique) as $instrument) {
            $byId[(int) $instrument->getId()] = $instrument;
        }

        $resolved = [];

        foreach ($unique as $id) {
            if (isset($byId[$id])) {
                $resolved[] = $byId[$id];
            }
        }

        return $resolved;
    }
}
