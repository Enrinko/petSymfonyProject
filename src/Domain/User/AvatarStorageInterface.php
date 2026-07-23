<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Exception\UnsupportedAvatarImageException;

/**
 * Хранилище аватаров: принимает загруженный файл, возвращает публичный
 * относительный путь (например, "/uploads/avatars/7.webp").
 */
interface AvatarStorageInterface
{
    /**
     * @throws UnsupportedAvatarImageException файл не является поддерживаемым изображением
     */
    public function store(int $userId, string $sourcePath): string;

    public function remove(string $publicPath): void;
}
