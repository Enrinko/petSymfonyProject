<?php

declare(strict_types=1);

namespace App\Application\Client\Import;

use App\Application\Client\CreateClientCommand;
use App\Application\Client\TagResolver;
use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Shared\TransactionRunnerInterface;
use App\Domain\User\User;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ImportClientsHandler
{
    private const int MAX_ROWS = 5000;

    public function __construct(
        private ClientRepositoryInterface $clients,
        private TagResolver $tagResolver,
        private ValidatorInterface $validator,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    /**
     * @param list<ImportRow> $rows
     */
    public function __invoke(array $rows, DuplicatePolicy $policy, User $owner): ImportClientsResult
    {
        if (\count($rows) > self::MAX_ROWS) {
            return new ImportClientsResult(0, 0, 0, [[
                'line' => 0,
                'message' => sprintf('Слишком много строк: %d (максимум %d).', \count($rows), self::MAX_ROWS),
            ]]);
        }

        return $this->transactions->inTransaction(function () use ($rows, $policy, $owner): ImportClientsResult {
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($rows as $row) {
                $command = new CreateClientCommand($row->name, $row->email, $row->phone, $row->comment, $row->tags);
                $violations = $this->validator->validate($command);

                if (\count($violations) > 0) {
                    $messages = [];
                    foreach ($violations as $violation) {
                        $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                    }
                    $errors[] = ['line' => $row->line, 'message' => implode(' ', $messages)];
                    continue;
                }

                $existing = $row->email !== null && trim($row->email) !== ''
                    ? $this->clients->findByEmail(trim($row->email))
                    : null;

                if ($existing !== null) {
                    if ($policy === DuplicatePolicy::Skip) {
                        ++$skipped;
                        continue;
                    }

                    $existing->update($row->name, new \DateTimeImmutable(), $row->email, $row->phone, $row->comment);
                    $existing->syncTags($this->tagResolver->resolve($row->tags));
                    $this->clients->save($existing);
                    ++$updated;
                    continue;
                }

                $client = Client::create($row->name, $owner, new \DateTimeImmutable(), $row->email, $row->phone, $row->comment);
                $client->syncTags($this->tagResolver->resolve($row->tags));
                $this->clients->save($client);
                ++$created;
            }

            return new ImportClientsResult($created, $updated, $skipped, $errors);
        });
    }
}
