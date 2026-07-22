<?php

declare(strict_types=1);

namespace App\Domain\Repertoire;

use App\Domain\Client\Client;

interface RepertoirePieceRepositoryInterface
{
    public function find(int $id): ?RepertoirePiece;

    /**
     * Репертуар ученика: активные статусы сверху (порядок пути), новые выше.
     *
     * @return list<RepertoirePiece>
     */
    public function findByClient(Client $client): array;

    public function save(RepertoirePiece $piece): void;

    public function remove(RepertoirePiece $piece): void;
}
