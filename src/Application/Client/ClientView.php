<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Client\Client;
use App\Domain\Instrument\Instrument;
use App\Domain\Tag\Tag;

final readonly class ClientView
{
    /**
     * @param list<string>                                          $tags
     * @param list<array{id: int, name: string, category: string}> $instruments
     */
    private function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $comment,
        public array $tags,
        public array $instruments,
        public string $createdAt,
        public ?string $updatedAt,
        public ?string $archivedAt,
    ) {
    }

    public static function fromClient(Client $client): self
    {
        $tags = array_map(static fn (Tag $tag): string => $tag->getName(), $client->getTags());
        sort($tags);

        $instruments = array_map(
            static fn (Instrument $i): array => [
                'id' => (int) $i->getId(),
                'name' => $i->getName(),
                'category' => $i->getCategory()->value,
            ],
            $client->getInstruments(),
        );

        return new self(
            (int) $client->getId(),
            $client->getName(),
            $client->getEmail(),
            $client->getPhone(),
            $client->getComment(),
            $tags,
            $instruments,
            $client->getCreatedAt()->format(\DateTimeInterface::ATOM),
            $client->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            $client->getArchivedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
