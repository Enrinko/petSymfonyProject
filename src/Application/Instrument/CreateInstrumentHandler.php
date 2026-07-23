<?php

declare(strict_types=1);

namespace App\Application\Instrument;

use App\Domain\Instrument\Exception\InstrumentNameTakenException;
use App\Domain\Instrument\Instrument;
use App\Domain\Instrument\InstrumentRepositoryInterface;

final readonly class CreateInstrumentHandler
{
    public function __construct(
        private InstrumentRepositoryInterface $instruments,
    ) {
    }

    /**
     * @throws InstrumentNameTakenException
     */
    public function __invoke(CreateInstrumentCommand $command): Instrument
    {
        if ($this->instruments->findByName($command->name) !== null) {
            throw new InstrumentNameTakenException(sprintf('Instrument "%s" already exists.', trim($command->name)));
        }

        $instrument = Instrument::create($command->name, $command->categoryEnum(), $command->sortOrder);
        $this->instruments->save($instrument);

        return $instrument;
    }
}
