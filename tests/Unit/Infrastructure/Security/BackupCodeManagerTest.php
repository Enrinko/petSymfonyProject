<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Domain\User\User;
use App\Infrastructure\Security\BackupCodeManager;
use App\Tests\Fake\InMemoryBackupCodeRepository;
use PHPUnit\Framework\TestCase;

final class BackupCodeManagerTest extends TestCase
{
    private InMemoryBackupCodeRepository $repository;
    private BackupCodeManager $manager;
    private User $user;

    protected function setUp(): void
    {
        $this->repository = new InMemoryBackupCodeRepository();
        $this->manager = new BackupCodeManager($this->repository);
        $this->user = User::register('user@example.test', 'hash');
    }

    public function testRegenerateProducesEightUniqueCodes(): void
    {
        $codes = $this->manager->regenerate($this->user);

        self::assertCount(8, $codes);
        self::assertCount(8, array_unique($codes));

        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^\d{10}$/', $code);
            self::assertTrue($this->manager->isBackupCode($this->user, $code));
        }
    }

    public function testCodesAreStoredHashedNotPlaintext(): void
    {
        $codes = $this->manager->regenerate($this->user);

        // В хранилище — только объекты с bcrypt-проверкой; plaintext нигде не лежит
        self::assertCount(8, $this->repository->codes);
        self::assertTrue($this->repository->codes[0]->matches($codes[0]));
        self::assertFalse($this->repository->codes[0]->matches('0000000000'));
    }

    public function testBackupCodeIsSingleUse(): void
    {
        $codes = $this->manager->regenerate($this->user);
        $code = $codes[0];

        self::assertTrue($this->manager->isBackupCode($this->user, $code));
        $this->manager->invalidateBackupCode($this->user, $code);

        self::assertFalse($this->manager->isBackupCode($this->user, $code));
        // Остальные коды живы
        self::assertTrue($this->manager->isBackupCode($this->user, $codes[1]));
    }

    public function testRegenerateWipesPreviousCodes(): void
    {
        $old = $this->manager->regenerate($this->user);
        $this->manager->regenerate($this->user);

        self::assertFalse($this->manager->isBackupCode($this->user, $old[0]));
    }

    public function testForeignUserObjectIsIgnored(): void
    {
        self::assertFalse($this->manager->isBackupCode(new \stdClass(), '0123456789'));
    }
}
