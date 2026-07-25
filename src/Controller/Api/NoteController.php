<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Note\AddNoteCommand;
use App\Application\Note\AddNoteHandler;
use App\Application\Note\ListClientNotesHandler;
use App\Application\Note\ListClientNotesQuery;
use App\Application\Note\NoteView;
use App\Application\Note\RemoveNoteHandler;
use App\Application\Note\UpdateNoteCommand;
use App\Application\Note\UpdateNoteHandler;
use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;
use App\Domain\User\User;
use App\Infrastructure\Security\Voter\ClientVoter;
use App\Infrastructure\Security\Voter\NoteVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
final class NoteController extends AbstractController
{
    private const string CLIENT_NOT_FOUND = 'api.client.not_found';
    private const string NOTE_NOT_FOUND = 'api.note.not_found';

    #[Route('/clients/{id}/notes', name: 'api_client_notes_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function list(
        int $id,
        Request $request,
        ClientRepositoryInterface $clients,
        ListClientNotesHandler $handler,
    ): JsonResponse {
        $client = $this->findAccessibleClient($id, $clients);

        if ($client === null) {
            return ApiJson::error(self::CLIENT_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $page = $handler(new ListClientNotesQuery(
            $client,
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 20),
        ));

        return $this->json([
            'data' => array_map(
                fn (Note $note): NoteView => NoteView::fromNote($note, $this->isGranted(NoteVoter::MANAGE, $note)),
                $page->notes,
            ),
            'total' => $page->total,
            'page' => $page->page,
            'limit' => $page->limit,
        ]);
    }

    #[Route('/clients/{id}/notes', name: 'api_client_notes_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(
        int $id,
        Request $request,
        ClientRepositoryInterface $clients,
        ValidatorInterface $validator,
        AddNoteHandler $handler,
    ): JsonResponse {
        $client = $this->findAccessibleClient($id, $clients);

        if ($client === null) {
            return ApiJson::error(self::CLIENT_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new AddNoteCommand((string) ($payload['content'] ?? ''));

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        /** @var User $author */
        $author = $this->getUser();

        $note = $handler($command, $client, $author);

        return $this->json(NoteView::fromNote($note, true), Response::HTTP_CREATED);
    }

    #[Route('/notes/{id}', name: 'api_notes_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(
        int $id,
        Request $request,
        NoteRepositoryInterface $notes,
        ValidatorInterface $validator,
        UpdateNoteHandler $handler,
    ): JsonResponse {
        $note = $this->findVisibleNote($id, $notes);

        if ($note === null) {
            return ApiJson::error(self::NOTE_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted(NoteVoter::MANAGE, $note)) {
            return ApiJson::error(
                'Заметку можно редактировать только автору в течение 24 часов.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $command = new UpdateNoteCommand($id, (string) ($payload['content'] ?? ''));

        $violations = $validator->validate($command);

        if (\count($violations) > 0) {
            return ApiJson::validationError($violations);
        }

        return $this->json(NoteView::fromNote($handler($command), true));
    }

    #[Route('/notes/{id}', name: 'api_notes_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, NoteRepositoryInterface $notes, RemoveNoteHandler $handler): JsonResponse
    {
        $note = $this->findVisibleNote($id, $notes);

        if ($note === null) {
            return ApiJson::error(self::NOTE_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted(NoteVoter::MANAGE, $note)) {
            return ApiJson::error(
                'Заметку может удалить только автор в течение 24 часов.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $handler($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Клиент, видимый текущему пользователю; чужой = 404 (без раскрытия существования).
     */
    private function findAccessibleClient(int $id, ClientRepositoryInterface $clients): ?Client
    {
        $client = $clients->find($id);

        if ($client === null || !$this->isGranted(ClientVoter::ACCESS, $client)) {
            return null;
        }

        return $client;
    }

    /**
     * Заметка на видимом клиенте. Заметка недоступного клиента = 404;
     * видимая, но чужая — отдаётся вызывающему для проверки NOTE_MANAGE (403).
     */
    private function findVisibleNote(int $id, NoteRepositoryInterface $notes): ?Note
    {
        $note = $notes->find($id);

        if ($note === null || !$this->isGranted(ClientVoter::ACCESS, $note->getClient())) {
            return null;
        }

        return $note;
    }
}
