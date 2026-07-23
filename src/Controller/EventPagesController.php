<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Event\EventRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class EventPagesController extends AbstractController
{
    #[Route('/events', name: 'app_events', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('events/index.html.twig');
    }

    #[Route('/events/{id}', name: 'app_event', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, EventRepositoryInterface $events): Response
    {
        $event = $events->find($id);

        if ($event === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('events/show.html.twig', [
            'event_id' => $id,
            'event_title' => $event->getTitle(),
        ]);
    }

    /**
     * Печатная программа: серверный Twig + print-CSS, без React.
     */
    #[Route('/events/{id}/program', name: 'app_event_program', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function program(int $id, EventRepositoryInterface $events): Response
    {
        $event = $events->find($id);

        if ($event === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('events/program.html.twig', ['event' => $event]);
    }
}
