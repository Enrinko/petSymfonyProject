<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Application\Search\SearchProviderInterface;
use App\Application\Search\SearchResults;
use App\Domain\User\User;

final class FakeSearchProvider implements SearchProviderInterface
{
    /** @var list<array{query: string, owner: ?User}> */
    public array $calls = [];

    private SearchResults $results;

    private ?\Throwable $failure = null;

    public function __construct(?SearchResults $results = null)
    {
        $this->results = $results ?? new SearchResults([], [], []);
    }

    public function willThrow(\Throwable $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    public function search(string $query, ?User $owner): SearchResults
    {
        $this->calls[] = ['query' => $query, 'owner' => $owner];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->results;
    }
}
