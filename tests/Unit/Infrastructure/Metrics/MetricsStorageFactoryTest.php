<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Metrics;

use App\Infrastructure\Metrics\MetricsStorageFactory;
use PHPUnit\Framework\TestCase;
use Prometheus\Storage\Redis as RedisStorage;

/**
 * Конструктор Prometheus\Storage\Redis не подключается к серверу (соединение
 * ленивое — коннект произойдёт при первой операции), поэтому тесты не требуют
 * реального Redis. Проверяем только то, что доступно снаружи: конкретный класс
 * адаптера (сигнатура фабрики даёт лишь интерфейс Adapter — assertInstanceOf
 * по нему PHPStan сочтёт избыточным) и отсутствие исключений при разборе URL,
 * включая rawurldecode() из I1.
 */
final class MetricsStorageFactoryTest extends TestCase
{
    public function testFromRedisUrlWithoutPasswordReturnsAdapter(): void
    {
        $adapter = MetricsStorageFactory::fromRedisUrl('redis://redis:6379');

        self::assertInstanceOf(RedisStorage::class, $adapter);
    }

    public function testFromRedisUrlWithUrlEncodedPasswordReturnsAdapter(): void
    {
        // %40 => '@', %2F => '/' — спецсимволы, которые ломали бы auth без rawurldecode().
        $adapter = MetricsStorageFactory::fromRedisUrl('redis://:p%40ss%2Fword@host:6380');

        self::assertInstanceOf(RedisStorage::class, $adapter);
    }
}
