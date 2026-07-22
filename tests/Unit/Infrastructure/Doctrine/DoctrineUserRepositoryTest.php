<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine;

use App\Domain\User\Exception\EmailAlreadyInUseException;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\DoctrineUserRepository;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DoctrineUserRepositoryTest extends TestCase
{
    public function testConcurrentDuplicateEmailBecomesDomainException(): void
    {
        // EntityManagerInterface слишком широк для рукописного фейка —
        // здесь единично используется встроенный стаб PHPUnit.
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException(
            new UniqueConstraintViolationException(self::driverException(), null),
        );

        $repository = new DoctrineUserRepository($entityManager);

        $this->expectException(EmailAlreadyInUseException::class);

        $repository->save(User::register('race@example.com', 'hash'));
    }

    private static function driverException(): DriverExceptionInterface
    {
        return new class('duplicate key value violates unique constraint', 0) extends \Exception implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '23505';
            }
        };
    }
}
