<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\PasswordReset\PasswordResetTokenRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tokens:purge-expired',
    description: 'Удаляет истёкшие токены сброса пароля (плановая очистка, дополняет ленивую)',
)]
final class PurgeExpiredResetTokensCommand extends Command
{
    public function __construct(
        private readonly PasswordResetTokenRepositoryInterface $tokens,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $removed = $this->tokens->deleteExpired(new \DateTimeImmutable());

        $io->success(sprintf('Удалено истёкших токенов: %d', $removed));

        return Command::SUCCESS;
    }
}
