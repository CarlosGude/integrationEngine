<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Security\HostPolicy;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/** Guards the final URL, including token requests and request middleware changes. */
final class HostPolicyHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    public function __construct(HttpClientInterface $client, private readonly HostPolicy $policy)
    {
        $this->client = $client;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->policy->assertAllowed($url);
        // Redirect targets cannot bypass a host allowlist. Applications may follow
        // an approved destination explicitly through a new guarded request.
        $options['max_redirects'] = 0;

        return $this->client->request($method, $url, $options);
    }
}
