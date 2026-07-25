<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Event\Event;
use App\Domain\Event\EventKind;
use App\Domain\Event\EventProgramItem;
use App\Domain\Event\EventRepositoryInterface;
use App\Domain\Event\Exception\InvalidEventException;
use App\Domain\Repertoire\RepertoirePieceRepositoryInterface;
use App\Infrastructure\Security\Voter\ClientVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Мероприятия — общешкольные: видны каждому ROLE_USER.
 * Номер добавляется только с видимым текущему пользователю учеником.
 */
#[Route('/api/events')]
#[IsGranted('ROLE_USER')]
final class EventController extends AbstractController
{
    private const string NOT_FOUND = 'api.event.not_found';

    #[Route('', name: 'api_events_list', methods: ['GET'])]
    public function list(Request $request, EventRepositoryInterface $events): JsonResponse
    {
        $now = new \DateTimeImmutable('today');

        $items = $request->query->getBoolean('past')
            ? $events->findPast($now)
            : $events->findUpcoming($now);

        return $this->json([
            'data' => array_map(static fn (Event $event): array => self::view($event, withProgram: false), $items),
        ]);
    }

    #[Route('', name: 'api_events_create', methods: ['POST'])]
    public function create(Request $request, EventRepositoryInterface $events): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $errors = self::validateEventPayload($payload);

        if ($errors !== []) {
            return ApiJson::error('api.validation_failed', Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
        }

        $event = Event::create(
            (string) $payload['title'],
            EventKind::from((string) $payload['kind']),
            new \DateTimeImmutable((string) $payload['date']),
            isset($payload['venue']) ? (string) $payload['venue'] : null,
            isset($payload['description']) ? (string) $payload['description'] : null,
        );

        $events->save($event);

        return $this->json(self::view($event, withProgram: true), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_events_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, EventRepositoryInterface $events): JsonResponse
    {
        $event = $events->find($id);

        if ($event === null) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $this->json(self::view($event, withProgram: true));
    }

    #[Route('/{id}', name: 'api_events_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function update(int $id, Request $request, EventRepositoryInterface $events): JsonResponse
    {
        $event = $events->find($id);

        if ($event === null) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $errors = self::validateEventPayload($payload);

        if ($errors !== []) {
            return ApiJson::error('api.validation_failed', Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
        }

        $event->update(
            (string) $payload['title'],
            EventKind::from((string) $payload['kind']),
            new \DateTimeImmutable((string) $payload['date']),
            isset($payload['venue']) ? (string) $payload['venue'] : null,
            isset($payload['description']) ? (string) $payload['description'] : null,
        );

        $events->save($event);

        return $this->json(self::view($event, withProgram: true));
    }

    #[Route('/{id}', name: 'api_events_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function delete(int $id, EventRepositoryInterface $events): JsonResponse
    {
        $event = $events->find($id);

        if ($event === null) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $events->remove($event);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/program', name: 'api_events_program_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addItem(
        int $id,
        Request $request,
        EventRepositoryInterface $events,
        ClientRepositoryInterface $clients,
        RepertoirePieceRepositoryInterface $pieces,
    ): JsonResponse {
        $event = $events->find($id);

        if ($event === null) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $client = $clients->find((int) ($payload['clientId'] ?? 0));

        if ($client === null || !$this->isGranted(ClientVoter::ACCESS, $client)) {
            return ApiJson::error('api.client.inaccessible', Response::HTTP_NOT_FOUND);
        }

        $piece = null;

        if (isset($payload['pieceId'])) {
            $piece = $pieces->find((int) $payload['pieceId']);

            // Чужое и несуществующее произведение — одинаковый 404: иначе пара
            // 404/422 работает оракулом перебора id репертуара всей школы
            if ($piece === null || $piece->getClient()->getId() !== $client->getId()) {
                return ApiJson::error('api.event.piece_not_found', Response::HTTP_NOT_FOUND);
            }
        }

        $customTitle = isset($payload['customTitle']) ? (string) $payload['customTitle'] : null;

        // customTitle — varchar(160): без проверки длинная строка доходит до БД → 500
        if ($customTitle !== null && mb_strlen(trim($customTitle)) > 160) {
            return ApiJson::error('api.event.custom_title_too_long', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $event->addProgramItem($client, $piece, $customTitle);
        } catch (InvalidEventException) {
            // Не отдаём внутренний текст инварианта — единое понятное сообщение
            return ApiJson::error('api.event.piece_or_title_required', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $events->save($event);

        return $this->json(self::view($event, withProgram: true), Response::HTTP_CREATED);
    }

    #[Route('/{id}/program/{itemId}/move', name: 'api_events_program_move', methods: ['PATCH'], requirements: ['id' => '\d+', 'itemId' => '\d+'])]
    public function moveItem(int $id, int $itemId, Request $request, EventRepositoryInterface $events): JsonResponse
    {
        $event = $events->find($id);

        if ($event === null) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $item = self::itemOf($event, $itemId);

        if ($item === null || !$this->isGranted(ClientVoter::ACCESS, $item->getClient())) {
            // Чужой номер программы неотличим от отсутствующего — не даём
            // ни удалять/двигать чужого ученика, ни перечислять id
            return ApiJson::error('api.event.program_item_not_found', Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $direction = \is_array($payload) ? (string) ($payload['direction'] ?? '') : '';

        if (!\in_array($direction, ['up', 'down'], true)) {
            return ApiJson::error('direction должен быть up или down.', Response::HTTP_BAD_REQUEST);
        }

        $event->moveItem($item, $direction === 'up');
        $events->save($event);

        return $this->json(self::view($event, withProgram: true));
    }

    #[Route('/{id}/program/{itemId}', name: 'api_events_program_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'itemId' => '\d+'])]
    public function deleteItem(int $id, int $itemId, EventRepositoryInterface $events): JsonResponse
    {
        $event = $events->find($id);

        if ($event === null) {
            return ApiJson::error(self::NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $item = self::itemOf($event, $itemId);

        if ($item === null || !$this->isGranted(ClientVoter::ACCESS, $item->getClient())) {
            return ApiJson::error('api.event.program_item_not_found', Response::HTTP_NOT_FOUND);
        }

        $event->removeItem($item);
        $events->save($event);

        return $this->json(self::view($event, withProgram: true));
    }

    private static function itemOf(Event $event, int $itemId): ?EventProgramItem
    {
        foreach ($event->getProgram() as $item) {
            if ($item->getId() === $itemId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private static function validateEventPayload(array $payload): array
    {
        $errors = [];
        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '') {
            $errors['title'] = 'Укажите название мероприятия.';
        } elseif (mb_strlen($title) > 160) {
            $errors['title'] = 'Название длиннее 160 символов.';
        }

        if (!\in_array((string) ($payload['kind'] ?? ''), EventKind::values(), true)) {
            $errors['kind'] = 'Неизвестный вид мероприятия.';
        }

        try {
            new \DateTimeImmutable((string) ($payload['date'] ?? ''));
        } catch (\Exception) {
            $errors['date'] = 'Некорректная дата.';
        }

        // venue — varchar(160): без проверки длинная строка доходит до БД → 500
        if (mb_strlen(trim((string) ($payload['venue'] ?? ''))) > 160) {
            $errors['venue'] = 'Площадка длиннее 160 символов.';
        }

        if (mb_strlen(trim((string) ($payload['description'] ?? ''))) > 5000) {
            $errors['description'] = 'Описание длиннее 5000 символов.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private static function view(Event $event, bool $withProgram): array
    {
        $view = [
            'id' => (int) $event->getId(),
            'title' => $event->getTitle(),
            'kind' => $event->getKind()->value,
            'kindLabel' => $event->getKind()->label(),
            'date' => $event->getDate()->format(\DateTimeInterface::ATOM),
            'venue' => $event->getVenue(),
            'description' => $event->getDescription(),
            'programCount' => \count($event->getProgram()),
        ];

        if ($withProgram) {
            // clientId НЕ отдаём: имя исполнителя нужно для афиши, а внутренний id
            // чужого ученика — только вектор перебора (id→имя) для чужой базы.
            // Фронт оперирует id номера программы (item.id), не clientId.
            $view['program'] = array_map(static fn (EventProgramItem $item): array => [
                'id' => (int) $item->getId(),
                'clientName' => $item->getClient()->getName(),
                'title' => $item->displayTitle(),
                'composer' => $item->displayComposer(),
                'sortOrder' => $item->getSortOrder(),
            ], $event->getProgram());
        }

        return $view;
    }
}
