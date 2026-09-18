<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

abstract readonly class HttpClientAdapterBase implements ClientAdapterInterface
{
    abstract protected function parseResponse(HttpResponseInterface $response, string $identifier): array;

    protected function sendManySequentially(array $requests): array
    {
        $results = [];

        foreach ($requests as $key => $request) {
            try {
                $results[$key] = $this->send($request->action, $request->context, $request->headers);
            } catch (\Throwable $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }
}
