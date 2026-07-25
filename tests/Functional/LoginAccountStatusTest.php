<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Деактивированный аккаунт: статусное сообщение показывается ТОЛЬКО владельцу
 * с верным паролем (ActiveUserChecker::checkPostAuth — после сверки пароля).
 * Неверный пароль отдаёт общий ответ, чтобы статус не работал как оракул
 * существования аккаунта (user/status enumeration).
 */
final class LoginAccountStatusTest extends FunctionalTestCase
{
    public function testDeactivatedAccountRevealsStatusOnlyWithCorrectPassword(): void
    {
        $user = $this->deactivatedUser();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => self::STRONG_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Аккаунт деактивирован. Обратитесь к администратору.',
            $this->json()['message'],
        );
    }

    public function testDeactivatedAccountWithWrongPasswordDoesNotLeakStatus(): void
    {
        $user = $this->deactivatedUser();

        $this->jsonRequest('POST', '/api/login', [
            'email' => $user->getEmail(),
            'password' => 'totally-wrong-password',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Неверный email или пароль.', $this->json()['message']);
    }

    private function deactivatedUser(): User
    {
        $user = $this->createUser();
        $user->deactivate();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->flush();

        return $user;
    }
}
