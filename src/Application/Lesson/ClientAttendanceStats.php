<?php

declare(strict_types=1);

namespace App\Application\Lesson;

final readonly class ClientAttendanceStats
{
    /**
     * @param list<AttendanceDot> $recent новые сверху
     */
    public function __construct(
        public int $missed30,
        public int $held30,
        public bool $needsAttention,
        public array $recent,
    ) {
    }
}
