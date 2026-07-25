<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Lesson\ClientAttendanceStatsHandler;
use App\Domain\Client\ClientRepositoryInterface;
use App\Infrastructure\Security\Voter\ClientVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ClientAttendanceController extends AbstractController
{
    /**
     * Статистика посещаемости для карточки ученика.
     * Чужой клиент неотличим от несуществующего (404).
     */
    #[Route('/api/clients/{id}/attendance', name: 'api_client_attendance', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        int $id,
        ClientRepositoryInterface $clients,
        ClientAttendanceStatsHandler $handler,
    ): JsonResponse {
        $client = $clients->find($id);

        if ($client === null || !$this->isGranted(ClientVoter::ACCESS, $client)) {
            return ApiJson::error('api.client.not_found', Response::HTTP_NOT_FOUND);
        }

        return $this->json($handler($client));
    }
}
