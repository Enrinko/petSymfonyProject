<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

final class ElasticsearchClientFactory
{
    public static function fromUrl(string $url): Client
    {
        return ClientBuilder::create()
            ->setHosts([$url])
            ->build();
    }
}
