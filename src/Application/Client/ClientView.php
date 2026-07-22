<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Client\Client;

final readonly class ClientView
{
    private function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $comment,
        public string $createdAt,
        public ?string $updatedAt,
        public ?string $archivedAt,
    ) {
    }

    public static function fromClient(Client $client): self
    {
        return new self(
            (int) $client->getId(),
            $client->getName(),
            $client->getEmail(),
            $client->getPhone(),
            $client->getComment(),
            $client->getCreatedAt()->format(\DateTimeInterface::ATOM),
            $client->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            $client->getArchivedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
