<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLoggerInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Security\BackupCodeManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:2fa:reset',
    description: 'Аварийный сброс 2FA (потерян телефон и резервные коды) — секрет и коды стираются',
)]
final class ResetTwoFactorCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly BackupCodeManager $backupCodes,
        private readonly AuditLoggerInterface $audit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email пользователя');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            $io->error(sprintf('Пользователь "%s" не найден.', $email));

            return Command::FAILURE;
        }

        if (!$user->isTotpEnabled() && $user->getTotpSecretCiphertext() === null) {
            $io->warning('У пользователя и так нет 2FA — нечего сбрасывать.');

            return Command::SUCCESS;
        }

        $user->disableTotp();
        $this->users->save($user);
        $this->backupCodes->removeAll($user);
        $this->audit->log(AuditAction::TwoFactorDisabled, 'user', (string) $user->getId(), ['by' => 'cli']);

        $io->success(sprintf('2FA для %s сброшена. Вход снова только по паролю.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
