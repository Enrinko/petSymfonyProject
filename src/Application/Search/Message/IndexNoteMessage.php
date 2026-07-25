<?php

declare(strict_types=1);

namespace App\Application\Search\Message;

final readonly class IndexNoteMessage
{
    public function __construct(
        public int $noteId,
    ) {
    }
}
