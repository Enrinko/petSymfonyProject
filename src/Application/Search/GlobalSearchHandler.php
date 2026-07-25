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

    /**
     * Верхняя граница длины запроса: осмысленный поиск в имя/email/телефон/заметку
     * укладывается с запасом, а длинный ввод — только амплификация нагрузки на ES
     * (multi_match с fuzziness=AUTO) и ILIKE-фолбэк. Не отклоняем, а обрезаем.
     */
    private const int MAX_QUERY_LENGTH = 128;

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

        if (mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            $query = mb_substr($query, 0, self::MAX_QUERY_LENGTH);
        }

        return $this->provider->search($query, $owner);
    }
}
