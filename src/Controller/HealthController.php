<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/healthz', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/readyz', methods: ['GET'])]
    public function ready(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return new JsonResponse(
                ['status' => 'error', 'db' => 'unreachable'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
