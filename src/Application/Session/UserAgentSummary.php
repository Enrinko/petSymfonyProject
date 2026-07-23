<?php

declare(strict_types=1);

namespace App\Application\Session;

/**
 * Грубая сводка по User-Agent: браузер и ОС по таблице подстрок.
 * Точность «для карточки в профиле», без тяжёлых библиотек.
 */
final readonly class UserAgentSummary
{
    // Порядок важен: UA Edge/Opera содержат и "Chrome", и "Safari"
    private const array BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'Opera' => 'Opera',
        'YaBrowser' => 'Яндекс Браузер',
        'Firefox/' => 'Firefox',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    // Android содержит "Linux" — мобильные проверяются первыми
    private const array SYSTEMS = [
        'Android' => 'Android',
        'iPhone' => 'iOS',
        'iPad' => 'iOS',
        'Windows' => 'Windows',
        'Mac OS X' => 'macOS',
        'Linux' => 'Linux',
    ];

    private function __construct(
        public string $browser,
        public string $os,
    ) {
    }

    public static function parse(?string $userAgent): self
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return new self('Неизвестный браузер', '');
        }

        $browser = 'Неизвестный браузер';

        foreach (self::BROWSERS as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                $browser = $name;
                break;
            }
        }

        $os = '';

        foreach (self::SYSTEMS as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                $os = $name;
                break;
            }
        }

        return new self($browser, $os);
    }
}
