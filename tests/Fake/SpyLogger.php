<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Psr\Log\AbstractLogger;

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    public function lastLevel(): mixed
    {
        return $this->records === [] ? null : $this->records[array_key_last($this->records)]['level'];
    }

    public function lastMessage(): ?string
    {
        return $this->records === [] ? null : $this->records[array_key_last($this->records)]['message'];
    }
}
