<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchProviderInterface;
use App\Application\Search\SearchResults;
use App\Domain\User\User;
use Psr\Log\LoggerInterface;

/**
 * Прозрачный откат: недоступный/сломанный Elasticsearch не должен ломать
 * палитру — поиск молча продолжает работать на PostgreSQL ILIKE, а сбой
 * остаётся в логе предупреждением.
 */
final readonly class FallbackSearchProvider implements SearchProviderInterface
{
    public function __construct(
        private SearchProviderInterface $primary,
        private SearchProviderInterface $fallback,
        private LoggerInterface $logger,
    ) {
    }

    public function search(string $query, ?User $owner): SearchResults
    {
        try {
            return $this->primary->search($query, $owner);
        } catch (\Throwable $failure) {
            $this->logger->warning('Search engine unavailable, falling back to database search.', [
                'exception' => $failure::class,
                'reason' => $failure->getMessage(),
            ]);

            return $this->fallback->search($query, $owner);
        }
    }
}
