<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class TwoFactorCheckController
{
    /**
     * Заглушка для роутера: POST /2fa_check перехватывает firewall scheb
     * (two_factor.check_path) — до контроллера запрос не доходит.
     */
    #[Route('/2fa_check', name: 'app_2fa_check', methods: ['POST'])]
    public function check(): never
    {
        throw new \LogicException('Intercepted by the two-factor firewall.');
    }
}
