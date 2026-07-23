<?php

declare(strict_types=1);

namespace App\Application\Instrument;

use App\Domain\Instrument\InstrumentCategory;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInstrumentCommand
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название инструмента.', normalizer: 'trim')]
        #[Assert\Length(max: 80, maxMessage: 'Название не может быть длиннее {{ limit }} символов.')]
        public string $name,
        #[Assert\Choice(callback: [InstrumentCategory::class, 'values'], message: 'Неизвестная категория.')]
        public string $category,
        public int $sortOrder = 0,
    ) {
    }

    public function categoryEnum(): InstrumentCategory
    {
        return InstrumentCategory::from($this->category);
    }
}
