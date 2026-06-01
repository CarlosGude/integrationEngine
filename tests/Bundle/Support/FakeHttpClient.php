<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Support;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class FakeHttpClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        throw new \LogicException('FakeHttpClient is not meant to perform real requests.');
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \LogicException('FakeHttpClient is not meant to perform real requests.');
    }

    public function withOptions(array $options): static
    {
        return $this;
    }
}
