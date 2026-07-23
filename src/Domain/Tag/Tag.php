<?php

declare(strict_types=1);

namespace App\Domain\Tag;

use App\Domain\Tag\Exception\InvalidTagNameException;
use Doctrine\ORM\Mapping as ORM;

/**
 * Свободный тег сегментации («взрослый», «подготовка к конкурсу», «онлайн»).
 * Имя нормализуется в lower-case — «Вокал» и «вокал» это один тег.
 * Цвет чипа не хранится: фронт выводит его детерминированно из хэша имени.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tag')]
#[ORM\UniqueConstraint(name: 'uniq_tag_name', columns: ['name'])]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $name;

    private function __construct(string $name)
    {
        $this->name = self::normalizeName($name);
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public static function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        if ($name === '') {
            throw new InvalidTagNameException('Tag name must not be blank.');
        }

        return $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
