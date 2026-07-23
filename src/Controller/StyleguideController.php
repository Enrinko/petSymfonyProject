<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Живая витрина дизайн-системы «Ink & Brass»: все примитивы и токены
 * на одной странице. Регресс-инструмент: после правок SCSS открываешь
 * её (в обеих темах) вместо кликанья по пяти экранам.
 */
#[IsGranted('ROLE_ADMIN')]
final class StyleguideController extends AbstractController
{
    #[Route('/styleguide', name: 'app_styleguide', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('styleguide/index.html.twig', [
            'palette' => [
                'Чернила' => ['--ink-900', '--ink-800', '--ink-700', '--ink-600', '--ink-300', '--ink-200', '--ink-100'],
                'Бумага' => ['--paper', '--surface', '--line'],
                'Латунь' => ['--brass-600', '--brass-500', '--brass-400', '--brass-100'],
                'Текст' => ['--text', '--text-muted', '--text-faint', '--text-on-ink', '--text-on-ink-muted', '--chip-neutral-fg'],
                'Статусы' => ['--ok-700', '--ok-600', '--ok-100', '--err-700', '--err-600', '--err-100'],
                'Роли' => ['--role-admin-bg', '--role-admin-fg', '--role-mod-bg', '--role-mod-fg', '--role-user-bg', '--role-user-fg'],
            ],
        ]);
    }
}
