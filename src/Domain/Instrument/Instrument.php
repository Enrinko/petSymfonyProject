<?php

declare(strict_types=1);

namespace App\Domain\Instrument;

use App\Domain\Instrument\Exception\InvalidInstrumentNameException;
use Doctrine\ORM\Mapping as ORM;

/**
 * Инструмент справочника обучения (фортепиано, вокал, гитара…).
 * Глобальная номенклатура школы: редактирует только администратор.
 */
#[ORM\Entity]
#[ORM\Table(name: 'instrument')]
#[ORM\UniqueConstraint(name: 'uniq_instrument_name', columns: ['name'])]
class Instrument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(enumType: InstrumentCategory::class)]
    private InstrumentCategory $category;

    #[ORM\Column]
    private int $sortOrder;

    private function __construct(string $name, InstrumentCategory $category, int $sortOrder)
    {
        $this->name = self::normalizeName($name);
        $this->category = $category;
        $this->sortOrder = $sortOrder;
    }

    public static function create(string $name, InstrumentCategory $category, int $sortOrder = 0): self
    {
        return new self($name, $category, $sortOrder);
    }

    public function rename(string $name, InstrumentCategory $category): void
    {
        $this->name = self::normalizeName($name);
        $this->category = $category;
    }

    public function reorder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategory(): InstrumentCategory
    {
        return $this->category;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidInstrumentNameException('Instrument name must not be blank.');
        }

        return $name;
    }
}
