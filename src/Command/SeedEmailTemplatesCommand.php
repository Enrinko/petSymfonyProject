<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Email\EmailTemplate;
use App\Domain\Email\EmailTemplateRepositoryInterface;
use App\Infrastructure\Mailer\EmailTemplateDefaults;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Засев редактируемых шаблонов писем (RU + EN) значениями по умолчанию
 * (EmailTemplateDefaults). Идемпотентно: существующие записи НЕ перезаписывает
 * (сохраняет правки админа), если не передан --force.
 */
#[AsCommand(name: 'app:emails:seed', description: 'Засеять/обновить дефолтные шаблоны писем (RU/EN)')]
final class SeedEmailTemplatesCommand extends Command
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $templates,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Перезаписать существующие шаблоны дефолтами');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach (EmailTemplateDefaults::all() as $key => $locales) {
            foreach ($locales as $locale => $content) {
                $existing = $this->templates->find($key, $locale);

                if ($existing !== null) {
                    if (!$force) {
                        ++$skipped;
                        continue;
                    }

                    $existing->update($content['subject'], $content['html'], $content['text']);
                    $this->templates->save($existing);
                    ++$updated;
                    continue;
                }

                $this->templates->save(EmailTemplate::create(
                    $key,
                    $locale,
                    $content['subject'],
                    $content['html'],
                    $content['text'],
                ));
                ++$created;
            }
        }

        $io->success(sprintf('Шаблоны писем: создано %d, обновлено %d, пропущено %d.', $created, $updated, $skipped));

        return Command::SUCCESS;
    }
}
