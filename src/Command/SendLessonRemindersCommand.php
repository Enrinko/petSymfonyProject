<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Lesson\SendLessonRemindersHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * Ручной запуск рассылки напоминаний; по расписанию её же гоняет scheduler
 * (AsPeriodicTask раз в час) через общий messenger-воркер.
 */
#[AsCommand(
    name: 'app:lessons:send-reminders',
    description: 'Отправляет напоминания о занятиях, начинающихся в ближайшие N часов (app.lesson_reminder_hours)',
)]
#[AsPeriodicTask(frequency: '1 hour', schedule: 'default')]
final class SendLessonRemindersCommand extends Command
{
    public function __construct(
        private readonly SendLessonRemindersHandler $handler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sent = ($this->handler)(new \DateTimeImmutable());

        $io->success(sprintf('Напоминаний отправлено: %d', $sent));

        return Command::SUCCESS;
    }
}
