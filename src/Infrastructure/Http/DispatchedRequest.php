<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

final readonly class DispatchedRequest
{
    public function __construct(
        public HttpResponseInterface $response,
        public string $method,
        public string $path,
    ) {}
}
