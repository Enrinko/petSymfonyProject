<?php

declare(strict_types=1);

namespace App\Application\Instrument;

use App\Domain\Instrument\Exception\InstrumentNameTakenException;
use App\Domain\Instrument\Exception\InstrumentNotFoundException;
use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentRepositoryInterface;

final readonly class UpdateInstrumentHandler
{
    public function __construct(
        private InstrumentRepositoryInterface $instruments,
    ) {
    }

    /**
     * @throws InstrumentNotFoundException
     * @throws InstrumentNameTakenException
     */
    public function __invoke(UpdateInstrumentCommand $command): Instrument
    {
        $instrument = $this->instruments->find($command->instrumentId)
            ?? throw new InstrumentNotFoundException(sprintf('Instrument #%d not found.', $command->instrumentId));

        // Имя занято, если принадлежит ДРУГОМУ инструменту (переименование в тот же — ок).
        $byName = $this->instruments->findByName($command->name);

        if ($byName !== null && $byName->getId() !== $instrument->getId()) {
            throw new InstrumentNameTakenException(sprintf('Instrument "%s" already exists.', trim($command->name)));
        }

        $instrument->rename($command->name, $command->categoryEnum());
        $instrument->reorder($command->sortOrder);
        $this->instruments->save($instrument);

        return $instrument;
    }
}
