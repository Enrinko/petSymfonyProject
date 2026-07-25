<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Сообщения об ошибках API локализуются по локали пользователя:
 * ApiJson::error отдаёт ключ api.*, ApiErrorLocaleListener переводит его
 * по LocaleResolver (профиль пользователя → сессия → Accept-Language).
 */
final class ApiErrorLocaleTest extends FunctionalTestCase
{
    public function testControllerErrorIsEnglishForEnglishUser(): void
    {
        $user = $this->createUser();
        $user->changeLocale('en');
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);

        $this->jsonRequest('GET', '/api/clients/99999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Student not found.', $this->json()['message']);
    }

    public function testControllerErrorIsRussianForRussianUser(): void
    {
        $user = $this->createUser(); // locale=null → русский по Accept-Language теста

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);

        $this->jsonRequest('GET', '/api/clients/99999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Клиент не найден.', $this->json()['message']);
    }
}
