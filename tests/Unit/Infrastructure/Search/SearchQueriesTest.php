<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Search;

use App\Infrastructure\Search\SearchQueries;
use PHPUnit\Framework\TestCase;

final class SearchQueriesTest extends TestCase
{
    public function testClientsQueryIsFuzzyMultiMatchWithBoosts(): void
    {
        $params = SearchQueries::clients('иванов', null, 5);

        self::assertSame('clients', $params['index']);
        self::assertSame(5, $params['body']['size']);

        $multiMatch = $params['body']['query']['bool']['must'][0]['multi_match'];
        self::assertSame('иванов', $multiMatch['query']);
        self::assertSame('AUTO', $multiMatch['fuzziness']);
        self::assertSame(['name^3', 'name.prefix^2', 'email', 'phone'], $multiMatch['fields']);

        self::assertArrayNotHasKey('filter', $params['body']['query']['bool']);
    }

    public function testOwnerScopeBecomesTermFilter(): void
    {
        $params = SearchQueries::clients('иванов', 7, 5);

        self::assertSame(
            [['term' => ['owner_id' => 7]]],
            $params['body']['query']['bool']['filter'],
        );
    }

    public function testNotesQueryHasPlainHighlightFragment(): void
    {
        $params = SearchQueries::notes('гаммы', 3, 5);

        self::assertSame('notes', $params['index']);

        $match = $params['body']['query']['bool']['must'][0]['match']['content'];
        self::assertSame('гаммы', $match['query']);
        self::assertSame('AUTO', $match['fuzziness']);

        self::assertSame(
            [['term' => ['owner_id' => 3]]],
            $params['body']['query']['bool']['filter'],
        );

        $highlight = $params['body']['highlight'];
        // Пустые теги: фронт рендерит сниппет как plain-текст, разметка не нужна
        self::assertSame([''], $highlight['pre_tags']);
        self::assertSame([''], $highlight['post_tags']);
        self::assertSame(100, $highlight['fields']['content']['fragment_size']);
        self::assertSame(1, $highlight['fields']['content']['number_of_fragments']);
    }
}
