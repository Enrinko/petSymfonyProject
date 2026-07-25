<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Client\Client;
use App\Domain\Note\Note;

/**
 * Документы и маппинги индексов `clients` / `notes`.
 *
 * Индекс хранит только искомые поля и фильтры видимости (owner_id) —
 * данные для отображения всегда поднимаются из БД по id (search-then-hydrate),
 * поэтому переименование клиента не требует реиндекса его заметок.
 */
final class SearchDocuments
{
    public const string CLIENTS_INDEX = 'clients';
    public const string NOTES_INDEX = 'notes';

    /**
     * @return array{name: string, email: ?string, phone: ?string, owner_id: int, archived: bool, created_at: string}
     */
    public static function clientDocument(Client $client): array
    {
        return [
            'name' => $client->getName(),
            'email' => $client->getEmail(),
            'phone' => $client->getPhone(),
            'owner_id' => (int) $client->getOwner()->getId(),
            'archived' => $client->isArchived(),
            'created_at' => $client->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{content: string, owner_id: int, client_id: int, created_at: string}
     */
    public static function noteDocument(Note $note): array
    {
        return [
            'content' => $note->getContent(),
            'owner_id' => (int) $note->getClient()->getOwner()->getId(),
            'client_id' => (int) $note->getClient()->getId(),
            'created_at' => $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientsIndexBody(): array
    {
        return [
            'settings' => [
                'analysis' => [
                    'filter' => [
                        'name_edge' => ['type' => 'edge_ngram', 'min_gram' => 2, 'max_gram' => 15],
                    ],
                    'analyzer' => [
                        // «Поиск по мере ввода»: индекс режет имя на префиксы,
                        // а запрос токенизится обычным standard (иначе запрос
                        // тоже разложился бы на n-граммы и матчил бы всё подряд)
                        'name_prefix' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'name_edge'],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'dynamic' => 'strict',
                'properties' => [
                    'name' => [
                        'type' => 'text',
                        'analyzer' => 'russian',
                        'fields' => [
                            'prefix' => [
                                'type' => 'text',
                                'analyzer' => 'name_prefix',
                                'search_analyzer' => 'standard',
                            ],
                        ],
                    ],
                    // standard-токенизация телефона даёт числовые токены
                    // («+7 (912) 345-67-89» → 7, 912, 345…) — запрос «912» матчится
                    'email' => ['type' => 'text'],
                    'phone' => ['type' => 'text'],
                    'owner_id' => ['type' => 'long'],
                    'archived' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'date'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function notesIndexBody(): array
    {
        return [
            'mappings' => [
                'dynamic' => 'strict',
                'properties' => [
                    'content' => ['type' => 'text', 'analyzer' => 'russian'],
                    'owner_id' => ['type' => 'long'],
                    'client_id' => ['type' => 'long'],
                    'created_at' => ['type' => 'date'],
                ],
            ],
        ];
    }
}
