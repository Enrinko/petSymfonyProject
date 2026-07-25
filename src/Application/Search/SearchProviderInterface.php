<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\User\User;

/**
 * Движок глобального поиска. Запрос сюда приходит уже нормализованным
 * (trim + минимальная длина — забота GlobalSearchHandler).
 */
interface SearchProviderInterface
{
    public function search(string $query, ?User $owner): SearchResults;
}
