<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Client;

use App\Application\Client\Import\DuplicatePolicy;
use App\Application\Client\Import\ImportClientsHandler;
use App\Application\Client\Import\ImportRow;
use App\Application\Client\TagResolver;
use App\Domain\Client\Client;
use App\Domain\User\User;
use App\Tests\Fake\FakeTransactionRunner;
use App\Tests\Fake\InMemoryClientRepository;
use App\Tests\Fake\InMemoryTagRepository;
use App\Tests\Fake\SpySearchIndexQueue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class ImportClientsHandlerTest extends TestCase
{
    public function testCreatesValidRowsAndCollectsRowErrors(): void
    {
        $clients = new InMemoryClientRepository();
        $transactions = new FakeTransactionRunner();
        $queue = new SpySearchIndexQueue();
        $handler = self::handler($clients, $transactions, $queue);

        $result = $handler(
            [
                new ImportRow(2, 'Анна Скрипкина', 'anna@example.com', null, 'вокал', ['вокал']),
                new ImportRow(3, 'Х', null, null, null, []),
                new ImportRow(4, 'Пётр Клавишев', null, 'кривой-телефон', null, []),
            ],
            DuplicatePolicy::Skip,
            self::owner(),
        );

        self::assertSame(1, $result->created);
        self::assertSame(0, $result->updated);
        self::assertSame(0, $result->skipped);
        self::assertCount(2, $result->errors);
        self::assertSame(3, $result->errors[0]['line'], 'Короткое имя — ошибка строки 3');
        self::assertSame(4, $result->errors[1]['line'], 'Кривой телефон — ошибка строки 4');
        self::assertCount(1, $clients->saved);
        self::assertSame(1, $transactions->transactions, 'Импорт целиком в одной транзакции');
        self::assertSame([$clients->saved[0]->getId()], $queue->queuedClients, 'Импортированный клиент уходит в индекс');
    }

    public function testDuplicateEmailSkippedByDefaultPolicy(): void
    {
        $owner = self::owner();
        $existing = Client::create('Анна Старая', $owner, new \DateTimeImmutable(), 'anna@example.com');
        $clients = new InMemoryClientRepository()->withClient(1, $existing);

        $queue = new SpySearchIndexQueue();
        $result = self::handler($clients, null, $queue)(
            [new ImportRow(2, 'Анна Новая', 'ANNA@example.com', null, null, [])],
            DuplicatePolicy::Skip,
            $owner,
        );

        self::assertSame(0, $result->created);
        self::assertSame(1, $result->skipped);
        self::assertSame('Анна Старая', $existing->getName(), 'Skip не трогает существующую запись');
        self::assertCount(0, $clients->saved);
        self::assertSame([], $queue->queuedClients, 'Пропущенный дубль не переиндексируется');
    }

    public function testDuplicateEmailUpdatedWhenPolicyIsUpdate(): void
    {
        $owner = self::owner();
        $existing = Client::create('Анна Старая', $owner, new \DateTimeImmutable(), 'anna@example.com');
        $clients = new InMemoryClientRepository()->withClient(1, $existing);
        $handler = self::handler($clients);

        $result = $handler(
            [new ImportRow(2, 'Анна Обновлённая', 'anna@example.com', '+79001234567', null, ['вокал'])],
            DuplicatePolicy::Update,
            $owner,
        );

        self::assertSame(1, $result->updated);
        self::assertSame('Анна Обновлённая', $existing->getName());
        self::assertSame('+79001234567', $existing->getPhone());
        self::assertSame(['вокал'], array_map(static fn ($t) => $t->getName(), $existing->getTags()));
        self::assertCount(1, $clients->saved);
    }

    public function testDuplicateEmailOfAnotherOwnerCreatesInsteadOfUpdating(): void
    {
        $teacherB = User::register('b@example.com', 'hash');
        $foreign = Client::create('Ученик B', $teacherB, new \DateTimeImmutable(), 'shared@example.com');
        $clients = new InMemoryClientRepository()->withClient(1, $foreign);

        $teacherA = User::register('a@example.com', 'hash');
        $queue = new SpySearchIndexQueue();

        $result = self::handler($clients, null, $queue)(
            [new ImportRow(2, 'Мой ученик', 'shared@example.com', null, null, [])],
            DuplicatePolicy::Update,
            $teacherA,
        );

        self::assertSame(1, $result->created, 'Чужой email не считается дублем — создаётся новый клиент');
        self::assertSame(0, $result->updated);
        self::assertSame('Ученик B', $foreign->getName(), 'Запись преподавателя B осталась нетронутой');
        self::assertCount(1, $clients->saved);
        self::assertSame($teacherA, $clients->saved[0]->getOwner());
    }

    public function testDuplicateEmailOfAnotherOwnerNotLeakedViaSkip(): void
    {
        $teacherB = User::register('b@example.com', 'hash');
        $foreign = Client::create('Ученик B', $teacherB, new \DateTimeImmutable(), 'shared@example.com');
        $clients = new InMemoryClientRepository()->withClient(1, $foreign);

        $result = self::handler($clients)(
            [new ImportRow(2, 'Мой ученик', 'shared@example.com', null, null, [])],
            DuplicatePolicy::Skip,
            User::register('a@example.com', 'hash'),
        );

        self::assertSame(1, $result->created, 'Skip не должен превращаться в оракул существования чужого email');
        self::assertSame(0, $result->skipped);
    }

    public function testTooManyRowsRejectedBeforeTransaction(): void
    {
        $transactions = new FakeTransactionRunner();
        $handler = self::handler(new InMemoryClientRepository(), $transactions);

        $rows = [];
        for ($i = 0; $i < 5001; ++$i) {
            $rows[] = new ImportRow($i + 2, 'Ученик ' . $i, null, null, null, []);
        }

        $result = $handler($rows, DuplicatePolicy::Skip, self::owner());

        self::assertSame(0, $result->created);
        self::assertCount(1, $result->errors);
        self::assertSame(0, $result->errors[0]['line']);
        self::assertSame(0, $transactions->transactions, 'Лимит проверяется до открытия транзакции');
    }

    private static function handler(
        InMemoryClientRepository $clients,
        ?FakeTransactionRunner $transactions = null,
        ?SpySearchIndexQueue $queue = null,
    ): ImportClientsHandler {
        return new ImportClientsHandler(
            $clients,
            new TagResolver(new InMemoryTagRepository()),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            $transactions ?? new FakeTransactionRunner(),
            $queue ?? new SpySearchIndexQueue(),
        );
    }

    private static function owner(): User
    {
        return User::register('teacher@example.com', 'hash');
    }
}
