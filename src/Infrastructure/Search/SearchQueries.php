<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

/**
 * DSL-запросы к Elasticsearch. Чистые фабрики массивов — контракт адаптера,
 * закреплённый юнитами без поднятого ES.
 */
final class SearchQueries
{
    /**
     * @return array{index: string, body: array<string, mixed>}
     */
    public static function clients(string $query, ?int $ownerId, int $limit): array
    {
        $bool = [
            'must' => [[
                'multi_match' => [
                    'query' => $query,
                    // Имя важнее контактов; prefix-поле ловит «поиск по мере ввода»
                    'fields' => ['name^3', 'name.prefix^2', 'email', 'phone'],
                    'fuzziness' => 'AUTO',
                ],
            ]],
        ];

        if ($ownerId !== null) {
            $bool['filter'] = [['term' => ['owner_id' => $ownerId]]];
        }

        return [
            'index' => SearchDocuments::CLIENTS_INDEX,
            'body' => [
                'size' => $limit,
                'query' => ['bool' => $bool],
            ],
        ];
    }

    /**
     * @return array{index: string, body: array<string, mixed>}
     */
    public static function notes(string $query, ?int $ownerId, int $limit): array
    {
        $bool = [
            'must' => [[
                'match' => [
                    'content' => ['query' => $query, 'fuzziness' => 'AUTO'],
                ],
            ]],
        ];

        if ($ownerId !== null) {
            $bool['filter'] = [['term' => ['owner_id' => $ownerId]]];
        }

        return [
            'index' => SearchDocuments::NOTES_INDEX,
            'body' => [
                'size' => $limit,
                'query' => ['bool' => $bool],
                'highlight' => [
                    // Пустые теги: сниппет остаётся plain-текстом, фронт не меняется
                    'pre_tags' => [''],
                    'post_tags' => [''],
                    'fields' => [
                        'content' => ['fragment_size' => 100, 'number_of_fragments' => 1],
                    ],
                ],
            ],
        ];
    }
}
