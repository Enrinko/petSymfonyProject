<?php

declare(strict_types=1);

namespace App\Application\Search\Message;

/**
 * «Клиент создан/изменён/архивирован — переиндексируй». В сообщении только id:
 * воркер поднимет свежую версию из БД (сообщение может лежать в очереди долго).
 */
final readonly class IndexClientMessage
{
    public function __construct(
        public int $clientId,
    ) {
    }
}
