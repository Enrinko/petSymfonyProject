<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Client\ClientRepositoryInterface;
use App\Infrastructure\Security\Voter\ClientVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientsPageController extends AbstractController
{
    #[Route('/clients', name: 'app_clients', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('clients/index.html.twig');
    }

    #[Route('/clients/{id}', name: 'app_client', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, ClientRepositoryInterface $clients): Response
    {
        $client = $clients->find($id);

        // Чужой ученик неотличим от несуществующего — как и в API.
        if ($client === null || !$this->isGranted(ClientVoter::ACCESS, $client)) {
            throw $this->createNotFoundException();
        }

        return $this->render('clients/show.html.twig', [
            'client_id' => (int) $client->getId(),
            'client_name' => $client->getName(),
        ]);
    }
}
