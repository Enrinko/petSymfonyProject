<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Dashboard\DashboardHandler;
use App\Domain\User\Role;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(DashboardHandler $handler): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Преподаватель — своя сводка; модератор/админ — по всей школе
        $owner = $this->isGranted(Role::Moderator->value) ? null : $user;

        return $this->json($handler($owner));
    }
}
