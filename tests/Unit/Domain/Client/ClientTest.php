<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Client;

use App\Domain\Client\Client;
use App\Domain\Client\Exception\InvalidClientNameException;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testCreateNormalizesFieldsAndStartsActive(): void
    {
        $now = new \DateTimeImmutable('2026-07-22 10:00:00');

        $client = Client::create('  Анна Скрипкина  ', self::owner(), $now, '  anna@example.com ', ' +7 900 000-00-00 ', null);

        self::assertSame('Анна Скрипкина', $client->getName());
        self::assertSame('anna@example.com', $client->getEmail());
        self::assertSame('+7 900 000-00-00', $client->getPhone());
        self::assertNull($client->getComment());
        self::assertSame($now, $client->getCreatedAt());
        self::assertNull($client->getUpdatedAt());
        self::assertFalse($client->isArchived());
    }

    public function testBlankNameIsRejected(): void
    {
        $this->expectException(InvalidClientNameException::class);

        Client::create('   ', self::owner(), new \DateTimeImmutable());
    }

    public function testUpdateChangesFieldsAndTouchesUpdatedAt(): void
    {
        $created = new \DateTimeImmutable('2026-07-22 10:00:00');
        $updated = new \DateTimeImmutable('2026-07-22 12:30:00');
        $client = Client::create('Анна', self::owner(), $created);

        $client->update('Анна Скрипкина', $updated, 'anna@example.com', null, 'вокал, вторник');

        self::assertSame('Анна Скрипкина', $client->getName());
        self::assertSame('вокал, вторник', $client->getComment());
        self::assertSame($updated, $client->getUpdatedAt());
    }

    public function testArchiveIsIdempotentAndRestoreClearsIt(): void
    {
        $client = Client::create('Анна', self::owner(), new \DateTimeImmutable('2026-07-22 10:00:00'));
        $firstArchive = new \DateTimeImmutable('2026-07-22 11:00:00');

        $client->archive($firstArchive);
        $client->archive(new \DateTimeImmutable('2026-07-22 12:00:00'));

        self::assertTrue($client->isArchived());
        self::assertSame($firstArchive, $client->getArchivedAt(), 'Повторный архив не двигает дату');

        $client->restore();

        self::assertFalse($client->isArchived());
        self::assertNull($client->getArchivedAt());
    }

    private static function owner(): User
    {
        return User::register('teacher@example.com', 'hash');
    }
}
