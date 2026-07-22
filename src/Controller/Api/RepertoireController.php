<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Client\Client;
use App\Domain\Client\ClientRepositoryInterface;
use App\Domain\Repertoire\Exception\InvalidPieceException;
use App\Domain\Repertoire\RepertoirePiece;
use App\Domain\Repertoire\RepertoirePieceRepositoryInterface;
use App\Infrastructure\Security\Voter\ClientVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
final class RepertoireController extends AbstractController
{
    private const string CLIENT_NOT_FOUND = 'Клиент не найден.';
    private const string PIECE_NOT_FOUND = 'Произведение не найдено.';
    private const int MAX_TITLE = 160;
    private const int MAX_COMPOSER = 120;
    private const int MAX_NOTE = 2000;

    #[Route('/clients/{id}/repertoire', name: 'api_client_repertoire_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function list(int $id, ClientRepositoryInterface $clients, RepertoirePieceRepositoryInterface $pieces): JsonResponse
    {
        $client = $this->visibleClient($id, $clients);

        if ($client === null) {
            return ApiJson::error(self::CLIENT_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => array_map(self::view(...), $pieces->findByClient($client)),
        ]);
    }

    #[Route('/clients/{id}/repertoire', name: 'api_client_repertoire_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(
        int $id,
        Request $request,
        ClientRepositoryInterface $clients,
        RepertoirePieceRepositoryInterface $pieces,
    ): JsonResponse {
        $client = $this->visibleClient($id, $clients);

        if ($client === null) {
            return ApiJson::error(self::CLIENT_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $composer = trim((string) ($payload['composer'] ?? ''));

        if ($title === '' || mb_strlen($title) > self::MAX_TITLE) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['title' => $title === '' ? 'Укажите название произведения.' : 'Название длиннее 160 символов.'],
            );
        }

        if (mb_strlen($composer) > self::MAX_COMPOSER) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['composer' => 'Имя композитора длиннее 120 символов.'],
            );
        }

        $piece = RepertoirePiece::add($client, $title, $composer === '' ? null : $composer, new \DateTimeImmutable());
        $pieces->save($piece);

        return $this->json(self::view($piece), Response::HTTP_CREATED);
    }

    #[Route('/repertoire/{id}/advance', name: 'api_repertoire_advance', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function advance(int $id, RepertoirePieceRepositoryInterface $pieces): JsonResponse
    {
        return $this->transition($id, $pieces, static fn (RepertoirePiece $piece) => $piece->advance());
    }

    #[Route('/repertoire/{id}/back', name: 'api_repertoire_back', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function back(int $id, RepertoirePieceRepositoryInterface $pieces): JsonResponse
    {
        return $this->transition($id, $pieces, static fn (RepertoirePiece $piece) => $piece->stepBack());
    }

    #[Route('/repertoire/{id}/note', name: 'api_repertoire_note', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function note(int $id, Request $request, RepertoirePieceRepositoryInterface $pieces): JsonResponse
    {
        $piece = $this->visiblePiece($id, $pieces);

        if ($piece === null) {
            return ApiJson::error(self::PIECE_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return ApiJson::invalidJson();
        }

        $note = (string) ($payload['note'] ?? '');

        if (mb_strlen($note) > self::MAX_NOTE) {
            return ApiJson::error(
                'Данные не прошли валидацию.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['note' => 'Заметка длиннее 2000 символов.'],
            );
        }

        $piece->updateNote($note);
        $pieces->save($piece);

        return $this->json(self::view($piece));
    }

    #[Route('/repertoire/{id}', name: 'api_repertoire_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, RepertoirePieceRepositoryInterface $pieces): JsonResponse
    {
        $piece = $this->visiblePiece($id, $pieces);

        if ($piece === null) {
            return ApiJson::error(self::PIECE_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $pieces->remove($piece);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param callable(RepertoirePiece): void $operation
     */
    private function transition(int $id, RepertoirePieceRepositoryInterface $pieces, callable $operation): JsonResponse
    {
        $piece = $this->visiblePiece($id, $pieces);

        if ($piece === null) {
            return ApiJson::error(self::PIECE_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        try {
            $operation($piece);
        } catch (InvalidPieceException $e) {
            return ApiJson::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pieces->save($piece);

        return $this->json(self::view($piece));
    }

    private function visibleClient(int $id, ClientRepositoryInterface $clients): ?Client
    {
        $client = $clients->find($id);

        if ($client === null || !$this->isGranted(ClientVoter::ACCESS, $client)) {
            return null;
        }

        return $client;
    }

    private function visiblePiece(int $id, RepertoirePieceRepositoryInterface $pieces): ?RepertoirePiece
    {
        $piece = $pieces->find($id);

        if ($piece === null || !$this->isGranted(ClientVoter::ACCESS, $piece->getClient())) {
            return null;
        }

        return $piece;
    }

    /**
     * @return array{id: int, title: string, composer: ?string, status: string, statusLabel: string, note: ?string, startedAt: string, canAdvance: bool, canStepBack: bool}
     */
    private static function view(RepertoirePiece $piece): array
    {
        return [
            'id' => (int) $piece->getId(),
            'title' => $piece->getTitle(),
            'composer' => $piece->getComposer(),
            'status' => $piece->getStatus()->value,
            'statusLabel' => $piece->getStatus()->label(),
            'note' => $piece->getNote(),
            'startedAt' => $piece->getStartedAt()->format(\DateTimeInterface::ATOM),
            'canAdvance' => $piece->getStatus()->next() !== null,
            'canStepBack' => $piece->getStatus()->previous() !== null,
        ];
    }
}
