<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * frontend_i18n(): срез frontend.* каталога текущей локали (с fallback-слиянием)
 * для выгрузки в <script data-i18n> — словарь React-фронтенда (assets/react/i18n.ts).
 */
final class FrontendI18nExtension extends AbstractExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('frontend_i18n', $this->frontendMessages(...)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function frontendMessages(): array
    {
        if (!$this->translator instanceof TranslatorBagInterface) {
            return [];
        }

        // Цепочка fallback-каталогов снизу вверх: ru-значения перекрываются текущей локалью
        $catalogues = [];

        for ($catalogue = $this->translator->getCatalogue(); $catalogue !== null; $catalogue = $catalogue->getFallbackCatalogue()) {
            $catalogues[] = $catalogue;
        }

        $messages = [];

        foreach (array_reverse($catalogues) as $catalogue) {
            foreach ($catalogue->all('messages') as $key => $value) {
                if (str_starts_with($key, 'frontend.')) {
                    $messages[$key] = $value;
                }
            }
        }

        return $messages;
    }
}
