<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Настоящий переводчик поверх настоящих каталогов translations/*.yaml:
 * юнит-тесты ассертят строки, которые реально видит пользователь,
 * и падают при рассинхроне ключа с каталогом.
 */
final class CatalogueTranslatorFactory
{
    public static function create(string $locale = 'ru'): Translator
    {
        $dir = \dirname(__DIR__, 2) . '/translations';

        $translator = new Translator($locale);
        $translator->setFallbackLocales(['ru']);
        $translator->addLoader('yaml', new YamlFileLoader());

        foreach (['ru', 'en'] as $catalogueLocale) {
            $translator->addResource('yaml', $dir . "/messages.{$catalogueLocale}.yaml", $catalogueLocale);
            $translator->addResource('yaml', $dir . "/validators.{$catalogueLocale}.yaml", $catalogueLocale, 'validators');
        }

        return $translator;
    }
}
