<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

/**
 * Помощник для безопасного «contains»-поиска через LIKE.
 *
 * Значение всегда биндится параметром (SQL-инъекции нет), но пользовательские
 * `%`/`_`/`\` внутри него — это wildcards LIKE: без экранирования `?q=%` матчит
 * всё, а шаблон вида `%_%_%…` заставляет PostgreSQL экспоненциально бэктрекать.
 * Экранируем метасимволы обратным слэшем — DQL-запрос обязан объявить `ESCAPE '\'`.
 */
final class LikePattern
{
    public const string ESCAPE_CHAR = '\\';

    /**
     * Экранированный шаблон `%value%` для поиска подстроки.
     * Вызывающий сам приводит регистр (обычно mb_strtolower + LOWER(column)).
     */
    public static function contains(string $value): string
    {
        return '%' . self::escape($value) . '%';
    }

    private static function escape(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
