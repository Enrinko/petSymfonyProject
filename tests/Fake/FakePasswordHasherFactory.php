<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class FakePasswordHasherFactory implements PasswordHasherFactoryInterface
{
    public function getPasswordHasher(object|string $user): PasswordHasherInterface
    {
        return new class implements PasswordHasherInterface {
            public function hash(#[\SensitiveParameter] string $plainPassword): string
            {
                return 'hashed:' . $plainPassword;
            }

            public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool
            {
                return $hashedPassword === 'hashed:' . $plainPassword;
            }

            public function needsRehash(string $hashedPassword): bool
            {
                return false;
            }
        };
    }
}
