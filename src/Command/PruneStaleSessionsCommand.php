<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Session\UserSessionRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * Чистка записей о сессиях без активности: сами PHP-сессии в Redis
 * истекают по TTL, а карточки в профиле не должны показывать призраков.
 */
#[AsCommand(
    name: 'app:sessions:prune-stale',
    description: 'Удаляет записи о сессиях без активности дольше N дней (по умолчанию 7)',
)]
#[AsPeriodicTask(frequency: '1 day', schedule: 'default')]
final class PruneStaleSessionsCommand extends Command
{
    public function __construct(
        private readonly UserSessionRepositoryInterface $sessions,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('keep-days', null, InputOption::VALUE_REQUIRED, 'Сколько дней неактивности хранить', '7');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keepDays = max(1, (int) $input->getOption('keep-days'));

        $removed = $this->sessions->removeStale(new \DateTimeImmutable(sprintf('-%d days', $keepDays)));

        $io->success(sprintf('Удалено неактивных сессий: %d', $removed));

        return Command::SUCCESS;
    }
}
