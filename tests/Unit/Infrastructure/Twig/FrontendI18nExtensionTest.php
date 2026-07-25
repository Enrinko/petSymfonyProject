<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Twig;

use App\Infrastructure\Twig\FrontendI18nExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class FrontendI18nExtensionTest extends TestCase
{
    public function testReturnsOnlyFrontendSliceWithFallbackMerge(): void
    {
        $translator = new Translator('en');
        $translator->setFallbackLocales(['ru']);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'frontend.auth.submit' => 'Войти',
            'frontend.common.error' => 'Ошибка',
            'layout.nav.overview' => 'Обзор',
        ], 'ru');
        $translator->addResource('array', [
            'frontend.auth.submit' => 'Sign in',
        ], 'en');

        $messages = new FrontendI18nExtension($translator)->frontendMessages();

        self::assertSame(
            [
                // en перекрывает ru; ключ без en-перевода приходит из fallback
                'frontend.auth.submit' => 'Sign in',
                'frontend.common.error' => 'Ошибка',
            ],
            $messages,
        );
    }

    public function testRuLocaleReturnsRuValues(): void
    {
        $translator = new Translator('ru');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['frontend.auth.submit' => 'Войти'], 'ru');

        self::assertSame(
            ['frontend.auth.submit' => 'Войти'],
            new FrontendI18nExtension($translator)->frontendMessages(),
        );
    }
}
