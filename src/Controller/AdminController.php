<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('admin/users.html.twig', [
            'currentUserId' => $user->getId(),
        ]);
    }

    #[Route('/admin/instruments', name: 'app_admin_instruments', methods: ['GET'])]
    public function instruments(): Response
    {
        return $this->render('admin/instruments.html.twig');
    }

    #[Route('/admin/audit', name: 'app_admin_audit', methods: ['GET'])]
    public function audit(): Response
    {
        return $this->render('admin/audit.html.twig');
    }
}
