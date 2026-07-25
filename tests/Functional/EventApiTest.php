<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Client\Client;
use App\Domain\Event\Event;
use App\Domain\Event\EventKind;
use App\Domain\Repertoire\RepertoirePiece;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Авторизация мероприятий: события общешкольные (создаёт любой ROLE_USER),
 * но правка/удаление события — ROLE_MODERATOR (function-level), а операции с
 * номером программы — object-level по владельцу ученика (ClientVoter).
 * Плюс: репертуар-oracle (единый 404) и length-валидация (нет 500).
 */
final class EventApiTest extends FunctionalTestCase
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function loginAs(User $user): void
    {
        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);
    }

    private function persistClient(User $owner, string $name): Client
    {
        $client = Client::create($name, $owner, new \DateTimeImmutable());
        $this->em()->persist($client);
        $this->em()->flush();

        return $client;
    }

    private function persistEvent(): Event
    {
        $event = Event::create('Отчётный концерт', EventKind::Concert, new \DateTimeImmutable('+1 week'), null, null);
        $this->em()->persist($event);
        $this->em()->flush();

        return $event;
    }

    public function testPlainUserCannotDeleteEvent(): void
    {
        $event = $this->persistEvent();
        $this->loginAs($this->createUser());

        $this->jsonRequest('DELETE', '/api/events/' . $event->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testModeratorCanDeleteEvent(): void
    {
        $event = $this->persistEvent();
        $this->loginAs($this->createUser(roles: ['ROLE_MODERATOR']));

        $this->jsonRequest('DELETE', '/api/events/' . $event->getId());

        self::assertResponseStatusCodeSame(204);
    }

    public function testPlainUserCannotUpdateEvent(): void
    {
        $event = $this->persistEvent();
        $this->loginAs($this->createUser());

        $this->jsonRequest('PATCH', '/api/events/' . $event->getId(), [
            'title' => 'Взлом',
            'kind' => 'concert',
            'date' => '2030-01-01T18:00:00+00:00',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCannotAddForeignClientToProgram(): void
    {
        $foreignClient = $this->persistClient($this->createUser(), 'Ученик B');
        $event = $this->persistEvent();
        $this->loginAs($this->createUser());

        $this->jsonRequest('POST', '/api/events/' . $event->getId() . '/program', [
            'clientId' => $foreignClient->getId(),
            'customTitle' => 'Этюд',
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotDeleteForeignProgramItem(): void
    {
        $clientB = $this->persistClient($this->createUser(), 'Ученик B');
        $event = $this->persistEvent();
        $item = $event->addProgramItem($clientB, null, 'Ноктюрн');
        $this->em()->flush();

        $this->loginAs($this->createUser());

        $this->jsonRequest('DELETE', '/api/events/' . $event->getId() . '/program/' . $item->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testForeignPieceIsNotFoundNotUnprocessable(): void
    {
        $clientB = $this->persistClient($this->createUser(), 'Ученик B');
        $piece = RepertoirePiece::add($clientB, 'Соната', 'Бетховен', new \DateTimeImmutable());
        $this->em()->persist($piece);
        $this->em()->flush();

        $teacherA = $this->createUser();
        $clientA = $this->persistClient($teacherA, 'Ученик A');
        $event = $this->persistEvent();
        $this->loginAs($teacherA);

        // Свой ученик, но чужое произведение → 404 (не 422): пара кодов не должна
        // работать оракулом существования id репертуара
        $this->jsonRequest('POST', '/api/events/' . $event->getId() . '/program', [
            'clientId' => $clientA->getId(),
            'pieceId' => $piece->getId(),
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTooLongVenueIsRejectedNotServerError(): void
    {
        $this->loginAs($this->createUser(roles: ['ROLE_MODERATOR']));

        $this->jsonRequest('POST', '/api/events', [
            'title' => 'Концерт',
            'kind' => 'concert',
            'date' => '2030-05-01T18:00:00+00:00',
            'venue' => str_repeat('я', 161),
        ]);

        self::assertResponseStatusCodeSame(422);
    }
}
