<?php

declare(strict_types=1);

namespace App\Application\Lesson;

use App\Domain\Lesson\Lesson;

interface LessonReminderMailerInterface
{
    public function sendReminder(Lesson $lesson): void;
}
