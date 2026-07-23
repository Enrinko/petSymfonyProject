<?php

declare(strict_types=1);

namespace App\Domain\Repertoire;

/**
 * Путь произведения: только вперёд (advance), назад — на один шаг (stepBack).
 */
enum PieceStatus: string
{
    case Learning = 'learning';
    case Memorizing = 'memorizing';
    case Ready = 'ready';
    case InRepertoire = 'in_repertoire';

    public function label(): string
    {
        return match ($this) {
            self::Learning => 'Разбираем',
            self::Memorizing => 'Учим наизусть',
            self::Ready => 'Готово к выступлению',
            self::InRepertoire => 'В репертуаре',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Learning => self::Memorizing,
            self::Memorizing => self::Ready,
            self::Ready => self::InRepertoire,
            self::InRepertoire => null,
        };
    }

    public function previous(): ?self
    {
        return match ($this) {
            self::Learning => null,
            self::Memorizing => self::Learning,
            self::Ready => self::Memorizing,
            self::InRepertoire => self::Ready,
        };
    }
}
