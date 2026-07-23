<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/** Поле request_id в каждой записи каждого канала. */
#[AsMonologProcessor]
final readonly class RequestIdProcessor
{
    public function __construct(
        private RequestIdProvider $requestId,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['request_id'] = $this->requestId->get();

        return $record;
    }
}
