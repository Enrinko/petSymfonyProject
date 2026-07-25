<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\User\User;

/**
 * Палитра Ctrl+K: до 5 совпадений на группу, поиск и по архивным
 * (сценарий «найти и перейти» важнее фильтров списка).
 * Движок — за портом: Elasticsearch с фолбэком на ILIKE (см. services.yaml).
 */
final readonly class GlobalSearchHandler
{
    private const int MIN_QUERY_LENGTH = 2;

    public function __construct(
        private SearchProviderInterface $provider,
    ) {
    }

    public function __invoke(string $query, ?User $owner): SearchResults
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return new SearchResults([], [], []);
        }

        return $this->provider->search($query, $owner);
    }
}
