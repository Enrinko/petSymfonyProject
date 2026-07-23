<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Application\Lesson\LessonReminderMailerInterface;
use App\Domain\Lesson\Lesson;

final class SpyLessonReminderMailer implements LessonReminderMailerInterface
{
    /**
     * @var list<Lesson>
     */
    public array $sent = [];

    public function sendReminder(Lesson $lesson): void
    {
        $this->sent[] = $lesson;
    }
}
