<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Client\Client;

final readonly class ClientHitView
{
    private function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public bool $archived,
    ) {
    }

    public static function fromClient(Client $client): self
    {
        return new self(
            (int) $client->getId(),
            $client->getName(),
            $client->getEmail(),
            $client->getPhone(),
            $client->isArchived(),
        );
    }
}
