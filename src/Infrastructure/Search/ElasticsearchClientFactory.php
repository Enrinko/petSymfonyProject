<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

final class ElasticsearchClientFactory
{
    public static function fromUrl(string $url): Client
    {
        $builder = ClientBuilder::create();

        // Прод: ES с xpack.security → URL вида http://elastic:pass@es:9200.
        // Достаём креды и передаём отдельно, host — без userinfo (детерминированно).
        $parts = parse_url($url);

        if (\is_array($parts) && isset($parts['user'])) {
            $host = sprintf(
                '%s://%s%s',
                $parts['scheme'] ?? 'http',
                $parts['host'] ?? 'localhost',
                isset($parts['port']) ? ':' . $parts['port'] : '',
            );

            $builder
                ->setHosts([$host])
                ->setBasicAuthentication($parts['user'], $parts['pass'] ?? '');
        } else {
            // Dev/test: URL без креденшелов — security выключен, изоляция сетью
            $builder->setHosts([$url]);
        }

        return $builder->build();
    }
}
