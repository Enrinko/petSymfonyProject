<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * Одна точка входа: гость видит публичный лендинг,
     * авторизованный — рабочий «Пульт».
     */
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        if (!$this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->render('landing/index.html.twig');
        }

        return $this->render('home/index.html.twig');
    }
}
