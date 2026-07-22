<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Search\GlobalSearchHandler;
use App\Domain\User\Role;
use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SearchController extends AbstractController
{
    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request, GlobalSearchHandler $handler): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $results = $handler(
            (string) $request->query->get('q', ''),
            // Та же модель видимости, что у списка: преподаватель — своё, модератор/админ — всё
            $this->isGranted(Role::Moderator->value) ? null : $user,
        );

        return $this->json($results);
    }
}
