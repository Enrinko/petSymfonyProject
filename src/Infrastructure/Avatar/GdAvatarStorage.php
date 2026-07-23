<?php

declare(strict_types=1);

namespace App\Infrastructure\Avatar;

use App\Domain\User\AvatarStorageInterface;
use App\Domain\User\Exception\UnsupportedAvatarImageException;

/**
 * GD-адаптер: валидирует картинку по СОДЕРЖИМОМУ (не по расширению),
 * вписывает центр-кропом в квадрат 256×256 и сохраняет как webp
 * в public/uploads/avatars/{userId}.webp (перезапись = смена аватара).
 */
final readonly class GdAvatarStorage implements AvatarStorageInterface
{
    private const int SIZE = 256;
    private const array SUPPORTED_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function __construct(
        private string $publicDir,
        private string $publicPrefix = '/uploads/avatars',
    ) {
    }

    public function store(int $userId, string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);

        if ($info === false || !\in_array($info[2], self::SUPPORTED_TYPES, true)) {
            throw new UnsupportedAvatarImageException('Not a supported image (jpeg/png/webp).');
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            default => @imagecreatefromwebp($sourcePath),
        };

        if ($source === false) {
            throw new UnsupportedAvatarImageException('Image is corrupted or unreadable.');
        }

        $target = $this->resizeToSquare($source, $info[0], $info[1]);
        imagedestroy($source);

        $dir = $this->publicDir . $this->publicPrefix;

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($target);

            throw new \RuntimeException(sprintf('Cannot create avatar directory "%s".', $dir));
        }

        $publicPath = sprintf('%s/%d.webp', $this->publicPrefix, $userId);

        if (!imagewebp($target, $this->publicDir . $publicPath, 85)) {
            imagedestroy($target);

            throw new \RuntimeException('Failed to write avatar file.');
        }

        imagedestroy($target);

        return $publicPath;
    }

    public function remove(string $publicPath): void
    {
        // Только внутри своего каталога — не даём удалить произвольный файл
        if (!str_starts_with($publicPath, $this->publicPrefix . '/')) {
            return;
        }

        $file = $this->publicDir . $publicPath;

        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function resizeToSquare(\GdImage $source, int $width, int $height): \GdImage
    {
        $side = min($width, $height);
        $srcX = intdiv($width - $side, 2);
        $srcY = intdiv($height - $side, 2);

        $target = imagecreatetruecolor(self::SIZE, self::SIZE);

        // Прозрачность png/webp сохраняется, не заливается чёрным
        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);

        return $target;
    }
}
