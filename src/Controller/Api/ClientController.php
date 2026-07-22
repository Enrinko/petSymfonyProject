<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Client\ArchiveClientHandler;
use App\Application\Client\ClientView;
use App\Application\Client\CreateClientCommand;
use App\Application\Client\CreateClientHandler;
use App\Application\Client\ListClientsHandler;
use App\Application\Client\ListClientsQuery;
use App\Application\Client\RestoreClientHandler;
use App\Application\Client\UpdateClientCommand;
use App\Application\Client\UpdateClientHandler;
use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Client\Exception\ClientNotFoundException;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Infrastructure\Security\Voter\ClientVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/clients')]
#[IsGranted('ROLE_USER')]
final class ClientController extends AbstractController
{
    private const string NOT_FOUND_MESSAGE = 'Клиент не найден.';

    #[Route('', name: 'api_clients_list', methods: ['GET'])]
    public function list(Request $request, ListClientsHandler $handler): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $tagsParam = trim((string) $request->query->get('tags', ''));

        $page = $handler(new ListClientsQuery(
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 20),
            (string) $request->query->get('search', ''),
            $request->query->getBoolean('archived'),
            // Преподаватель видит только своих учеников; модератор/админ — всех.
            $this->isGranted(Role::Moderator->value) ? null : $user,
            $tagsParam === '' ? [] : explode(',', $tagsParam),
        ));

        return $this->json($page);
    }

    #[Route('', name: 'api_clients_create', methods: ['POST'])]
    public function create(
        Request $request,
        ValidatorInterface $validator,
        CreateClientHandler $handler,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new CreateClientCommand(
            (string) ($payload['name'] ?? ''),
            self::optionalString($payload, 'email'),
            self::optionalString($payload, 'phone'),
            self::optionalString($payload, 'comment'),
            self::stringList($payload, 'tags'),
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        /** @var User $owner */
        $owner = $this->getUser();

        return $this->json(ClientView::fromClient($handler($command, $owner)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_clients_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, ClientRepositoryInterface $clients): JsonResponse
    {
        $client = $this->findAccessible($id, $clients);

        if ($client === null) {
            return ApiJson::error(self::NOT_FOUND_MESSAGE, Response::HTTP_NOT_FOUND);
        }

        return $this->json(ClientView::fromClient($client));
    }

    #[Route('/{id}', name: 'api_clients_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(
        int $id,
        Request $request,
        ValidatorInterface $validator,
        UpdateClientHandler $handler,
        ClientRepositoryInterface $clients,
    ): JsonResponse {
        if ($this->findAccessible($id, $clients) === null) {
            return ApiJson::error(self::NOT_FOUND_MESSAGE, Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new UpdateClientCommand(
            $id,
            (string) ($payload['name'] ?? ''),
            self::optionalString($payload, 'email'),
            self::optionalString($payload, 'phone'),
            self::optionalString($payload, 'comment'),
            self::stringList($payload, 'tags'),
        );

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        try {
            $client = $handler($command);
        } catch (ClientNotFoundException) {
            return ApiJson::error(self::NOT_FOUND_MESSAGE, Response::HTTP_NOT_FOUND);
        }

        return $this->json(ClientView::fromClient($client));
    }

    /**
     * DELETE — мягкое удаление: клиент архивируется и исчезает из списков,
     * физически запись не удаляется (восстановление — PATCH /{id}/restore).
     */
    #[Route('/{id}', name: 'api_clients_archive', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function archive(int $id, ArchiveClientHandler $handler, ClientRepositoryInterface $clients): JsonResponse
    {
        if ($this->findAccessible($id, $clients) === null) {
            return ApiJson::error(self::NOT_FOUND_MESSAGE, Response::HTTP_NOT_FOUND);
        }

        try {
            $handler($id);
        } catch (ClientNotFoundException) {
            return ApiJson::error(self::NOT_FOUND_MESSAGE, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/restore', name: 'api_clients_restore', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function restore(int $id, RestoreClientHandler $handler, ClientRepositoryInterface $clients): JsonResponse
    {
        if ($this->findAccessible($id, $clients) === null) {
            return ApiJson::error(self::NOT_FOUND_MESSAGE, Response::HTTP_NOT_FOUND);
        }

        try {
            $client = $handler($id);
        } catch (ClientNotFoundException) {
            return ApiJson::error(self::NOT_FOUND_MESSAGE, Response::HTTP_NOT_FOUND);
        }

        return $this->json(ClientView::fromClient($client));
    }

    /**
     * Клиент, к которому у текущего пользователя есть доступ.
     * Чужой клиент неотличим от несуществующего (404, а не 403) —
     * не раскрываем существование записей через перебор id.
     */
    private function findAccessible(int $id, ClientRepositoryInterface $clients): ?Client
    {
        $client = $clients->find($id);

        if ($client === null || !$this->isGranted(ClientVoter::ACCESS, $client)) {
            return null;
        }

        return $client;
    }

    /**
     * Пустые строки и пробелы приводим к null до валидации:
     * Regex/Email-констрейнты не должны спотыкаться о ' ' из форм.
     *
     * @param array<string, mixed> $payload
     */
    private static function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private static function stringList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): string => (string) $item,
            array_filter($value, static fn (mixed $item): bool => \is_scalar($item)),
        ));
    }
}
