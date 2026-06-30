<?php

declare(strict_types=1);

namespace App\Controller;

use App\Projects\ProjectRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectsController extends AbstractController
{
    #[Route('/projects', name: 'app_projects', methods: ['GET'])]
    public function index(ProjectRegistry $registry): Response
    {
        return $this->render('projects/index.html.twig', [
            'projects' => $registry->all(),
        ]);
    }
}
