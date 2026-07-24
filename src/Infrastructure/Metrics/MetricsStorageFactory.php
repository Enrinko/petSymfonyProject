<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use Prometheus\Storage\Adapter;
use Prometheus\Storage\Redis as RedisStorage;

/**
 * Хранилище метрик в Redis: FrankenPHP работает в worker-режиме,
 * несколько процессов должны суммировать счётчики в общем месте.
 * Префикс отделяет ключи метрик от сессий и cache.app.
 */
final readonly class MetricsStorageFactory
{
    public static function fromRedisUrl(string $redisUrl): Adapter
    {
        $parts = parse_url($redisUrl);

        RedisStorage::setPrefix('psy_metrics:');

        // parse_url() отдаёт host/pass как есть из строки URL, а Symfony при разборе
        // своих Redis DSN их URL-декодирует — без rawurldecode() пароль со спецсимволами
        // (%-энкоженный в REDIS_URL) молча ломает авторизацию только для метрик.
        return new RedisStorage([
            'host' => \is_array($parts) && isset($parts['host']) ? rawurldecode($parts['host']) : '127.0.0.1',
            'port' => \is_array($parts) ? ($parts['port'] ?? 6379) : 6379,
            'password' => \is_array($parts) && isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
            'timeout' => 0.5,
            'read_timeout' => '10',
            'persistent_connections' => false,
        ]);
    }
}
