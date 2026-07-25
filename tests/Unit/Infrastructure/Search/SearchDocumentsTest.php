<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Search;

use App\Domain\Client\Client;
use App\Domain\Note\Note;
use App\Domain\User\User;
use App\Infrastructure\Search\SearchDocuments;
use PHPUnit\Framework\TestCase;

final class SearchDocumentsTest extends TestCase
{
    public function testClientDocumentCarriesSearchAndVisibilityFields(): void
    {
        $owner = self::userWithId(7);
        $createdAt = new \DateTimeImmutable('2026-07-25T10:00:00+03:00');
        $client = Client::create('Анна Скрипкина', $owner, $createdAt, 'anna@example.com', '+7 912 345-67-89');

        $document = SearchDocuments::clientDocument($client);

        self::assertSame([
            'name' => 'Анна Скрипкина',
            'email' => 'anna@example.com',
            'phone' => '+7 912 345-67-89',
            'owner_id' => 7,
            'archived' => false,
            'created_at' => $createdAt->format(\DateTimeInterface::ATOM),
        ], $document);
    }

    public function testArchivedClientIsMarkedAndNullsBecomeEmpty(): void
    {
        $client = Client::create('Ушедшая Ученица', self::userWithId(1), new \DateTimeImmutable());
        $client->archive(new \DateTimeImmutable());

        $document = SearchDocuments::clientDocument($client);

        self::assertTrue($document['archived']);
        self::assertNull($document['email']);
        self::assertNull($document['phone']);
    }

    public function testNoteDocumentTakesOwnerFromClient(): void
    {
        $owner = self::userWithId(3);
        $client = self::clientWithId(11, $owner);
        $createdAt = new \DateTimeImmutable('2026-07-25T12:30:00+03:00');
        $note = Note::create($client, $owner, 'разобрали этюд Черни', $createdAt);

        $document = SearchDocuments::noteDocument($note);

        self::assertSame([
            'content' => 'разобрали этюд Черни',
            'owner_id' => 3,
            'client_id' => 11,
            'created_at' => $createdAt->format(\DateTimeInterface::ATOM),
        ], $document);
    }

    public function testMappingsAreStrictWithRussianAnalyzerAndEdgeNgram(): void
    {
        $clients = SearchDocuments::clientsIndexBody();
        $notes = SearchDocuments::notesIndexBody();

        self::assertSame('strict', $clients['mappings']['dynamic']);
        self::assertSame('strict', $notes['mappings']['dynamic']);

        self::assertSame('russian', $clients['mappings']['properties']['name']['analyzer']);
        self::assertSame('russian', $notes['mappings']['properties']['content']['analyzer']);

        $edge = $clients['settings']['analysis']['filter']['name_edge'];
        self::assertSame('edge_ngram', $edge['type']);
        self::assertSame(2, $edge['min_gram']);
        self::assertSame(15, $edge['max_gram']);

        // Поле «поиска по мере ввода» подключено к edge-ngram анализатору
        self::assertSame(
            'name_prefix',
            $clients['mappings']['properties']['name']['fields']['prefix']['analyzer'],
        );
    }

    private static function userWithId(int $id): User
    {
        $user = User::register('teacher@example.com', 'hash');
        self::forceId($user, $id);

        return $user;
    }

    private static function clientWithId(int $id, User $owner): Client
    {
        $client = Client::create('Клиент', $owner, new \DateTimeImmutable());
        self::forceId($client, $id);

        return $client;
    }

    private static function forceId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
