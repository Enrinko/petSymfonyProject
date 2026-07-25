<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\Search\SearchIndexInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Полная переиндексация поиска: пересоздаёт индексы и заливает все данные
 * bulk-чанками. Нужна после первого запуска, изменения маппингов или потери
 * индекса; пока идёт — поиск может отдавать неполные результаты.
 */
#[AsCommand(
    name: 'app:search:reindex',
    description: 'Пересоздаёт поисковые индексы Elasticsearch и переиндексирует клиентов и заметки',
)]
final class ReindexSearchCommand extends Command
{
    private const int CHUNK_SIZE = 500;

    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly NoteRepositoryInterface $notes,
        private readonly SearchIndexInterface $index,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->index->recreateIndices();

        // Архивные тоже в индексе: палитра ищет по архиву (флаг archived)
        $clientCount = $this->flushInChunks(
            $this->clients->iterateBySearch('', true),
            $this->index->indexClients(...),
        );
        $noteCount = $this->flushInChunks(
            $this->notes->iterateAll(),
            $this->index->indexNotes(...),
        );

        $io->success(sprintf('Переиндексировано: клиентов — %d, заметок — %d.', $clientCount, $noteCount));

        return Command::SUCCESS;
    }

    /**
     * @template T
     *
     * @param iterable<T>              $entities
     * @param callable(list<T>): void $flush
     */
    private function flushInChunks(iterable $entities, callable $flush): int
    {
        $chunk = [];
        $total = 0;

        foreach ($entities as $entity) {
            $chunk[] = $entity;
            ++$total;

            if (\count($chunk) >= self::CHUNK_SIZE) {
                $flush($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $flush($chunk);
        }

        return $total;
    }
}
