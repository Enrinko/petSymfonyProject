<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Avatar;

use App\Domain\User\Exception\UnsupportedAvatarImageException;
use App\Infrastructure\Avatar\GdAvatarStorage;
use PHPUnit\Framework\TestCase;

final class GdAvatarStorageTest extends TestCase
{
    private string $publicDir;
    private GdAvatarStorage $storage;

    protected function setUp(): void
    {
        if (!\function_exists('imagewebp')) {
            self::markTestSkipped('GD with webp support is not available.');
        }

        $this->publicDir = sys_get_temp_dir() . '/avatar-test-' . uniqid();
        mkdir($this->publicDir, 0775, true);
        $this->storage = new GdAvatarStorage($this->publicDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->publicDir . '/uploads/avatars/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->publicDir . '/uploads/avatars');
        @rmdir($this->publicDir . '/uploads');
        @rmdir($this->publicDir);
    }

    public function testStoresLandscapePngAsSquareWebp(): void
    {
        $source = $this->makePng(512, 300);

        $path = $this->storage->store(7, $source);
        @unlink($source);

        self::assertSame('/uploads/avatars/7.webp', $path);

        $info = getimagesize($this->publicDir . $path);
        self::assertNotFalse($info);
        self::assertSame([256, 256, IMAGETYPE_WEBP], [$info[0], $info[1], $info[2]]);
    }

    public function testRejectsNonImageFile(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'txt');
        \assert($source !== false);
        file_put_contents($source, '<?php echo "malicious"; // притворяется картинкой');

        $this->expectException(UnsupportedAvatarImageException::class);

        try {
            $this->storage->store(7, $source);
        } finally {
            @unlink($source);
        }
    }

    public function testRejectsGifEvenThoughGdSupportsIt(): void
    {
        $img = imagecreatetruecolor(64, 64);
        \assert($img !== false);
        $source = tempnam(sys_get_temp_dir(), 'gif');
        \assert($source !== false);
        imagegif($img, $source);
        imagedestroy($img);

        $this->expectException(UnsupportedAvatarImageException::class);

        try {
            $this->storage->store(7, $source);
        } finally {
            @unlink($source);
        }
    }

    public function testRemoveDeletesOnlyInsideAvatarDirectory(): void
    {
        $source = $this->makePng(64, 64);
        $path = $this->storage->store(9, $source);
        @unlink($source);
        self::assertFileExists($this->publicDir . $path);

        // Попытка выйти из каталога — тихо игнорируется
        $outside = $this->publicDir . '/index.php';
        file_put_contents($outside, 'app');
        $this->storage->remove('/index.php');
        self::assertFileExists($outside);
        @unlink($outside);

        $this->storage->remove($path);
        self::assertFileDoesNotExist($this->publicDir . $path);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function makePng(int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        \assert($img !== false);
        $file = tempnam(sys_get_temp_dir(), 'png');
        \assert($file !== false);
        imagepng($img, $file);
        imagedestroy($img);

        return $file;
    }
}
