<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Audit\AuditEventRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * Ретенция журнала безопасности. Планово выполняется messenger-воркером
 * (schedule 'default'); вручную: bin/console app:audit:prune --keep-days=90.
 */
#[AsCommand(
    name: 'app:audit:prune',
    description: 'Удаляет записи журнала безопасности старше N дней (по умолчанию 365)',
)]
#[AsPeriodicTask(frequency: '1 day', schedule: 'default')]
final class PruneAuditLogCommand extends Command
{
    public function __construct(
        private readonly AuditEventRepositoryInterface $events,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('keep-days', null, InputOption::VALUE_REQUIRED, 'Сколько дней хранить', '365');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keepDays = max(1, (int) $input->getOption('keep-days'));

        $removed = $this->events->pruneOlderThan(
            new \DateTimeImmutable(sprintf('-%d days', $keepDays)),
        );

        $io->success(sprintf('Удалено записей аудита старше %d дн.: %d', $keepDays, $removed));

        return Command::SUCCESS;
    }
}
