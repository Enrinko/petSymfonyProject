<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\User\Exception\UnknownRoleException;
use App\Domain\User\Role;
use App\Domain\User\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:promote',
    description: 'Выдаёт пользователю роль (по умолчанию ROLE_ADMIN) — для назначения первого администратора',
)]
final class PromoteUserCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email пользователя')
            ->addArgument('role', InputArgument::OPTIONAL, 'Роль', Role::Admin->value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $role = (string) $input->getArgument('role');

        $user = $this->users->findByEmail(mb_strtolower(trim($email)));

        if ($user === null) {
            $io->error(sprintf('Пользователь "%s" не найден.', $email));

            return Command::FAILURE;
        }

        try {
            $user->changeRoles([...$user->getRoles(), $role]);
        } catch (UnknownRoleException) {
            $io->error(sprintf('Неизвестная роль "%s". Допустимые: %s', $role, implode(', ', Role::values())));

            return Command::FAILURE;
        }

        $this->users->save($user);

        $io->success(sprintf('Пользователю %s выданы роли: %s', $user->getEmail(), implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
