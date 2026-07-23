<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Client\Client;
use App\Domain\Lesson\Attendance;
use App\Domain\Lesson\Lesson;
use App\Domain\Lesson\LessonRepositoryInterface;

/**
 * Статистика посещаемости ученика: точки последних занятий,
 * пропуски за 30 дней и сигнал «остывающего»:
 * 2+ пропуска подряд (отмены серию не рвут) или 3+ за 30 дней.
 */
final readonly class ClientAttendanceStatsHandler
{
    private const int RECENT_LIMIT = 10;
    private const int MISS_STREAK_THRESHOLD = 2;
    private const int MISSES_PER_MONTH_THRESHOLD = 3;

    public function __construct(
        private LessonRepositoryInterface $lessons,
    ) {
    }

    public function __invoke(Client $client): ClientAttendanceStats
    {
        $recent = $this->lessons->findRecentClosedForClient($client, self::RECENT_LIMIT);

        $monthAgo = new \DateTimeImmutable('-30 days');
        $missed30 = 0;
        $held30 = 0;

        foreach ($this->lessons->findClosedForClientSince($client, $monthAgo) as $lesson) {
            $attendance = $lesson->getAttendance();

            if ($attendance === Attendance::Attended || $attendance === Attendance::Missed) {
                ++$held30;

                if ($attendance === Attendance::Missed) {
                    ++$missed30;
                }
            }
        }

        $needsAttention = $missed30 >= self::MISSES_PER_MONTH_THRESHOLD
            || $this->missStreak($recent) >= self::MISS_STREAK_THRESHOLD;

        return new ClientAttendanceStats(
            $missed30,
            $held30,
            $needsAttention,
            array_map(static fn (Lesson $lesson): AttendanceDot => AttendanceDot::fromLesson($lesson), $recent),
        );
    }

    /**
     * Пропуски подряд, начиная с последнего занятия.
     * Отмены пропускаются (не рвут серию), «был» — обрывает.
     *
     * @param list<Lesson> $recentClosed новые сверху
     */
    private function missStreak(array $recentClosed): int
    {
        $streak = 0;

        foreach ($recentClosed as $lesson) {
            $attendance = $lesson->getAttendance();

            if ($attendance === Attendance::Missed) {
                ++$streak;
            } elseif ($attendance === Attendance::Attended) {
                break;
            }
            // отмены — continue: серию не рвут и не наращивают
        }

        return $streak;
    }
}
