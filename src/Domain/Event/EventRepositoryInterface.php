<?php

declare(strict_types=1);

namespace App\Domain\Event;

interface EventRepositoryInterface
{
    public function find(int $id): ?Event;

    /**
     * Ближайшие (date >= $from) по возрастанию даты.
     *
     * @return list<Event>
     */
    public function findUpcoming(\DateTimeImmutable $from): array;

    /**
     * Прошедшие (date < $before) по убыванию даты.
     *
     * @return list<Event>
     */
    public function findPast(\DateTimeImmutable $before): array;

    public function save(Event $event): void;

    public function remove(Event $event): void;
}
