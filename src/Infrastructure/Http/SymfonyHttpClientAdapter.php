<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\BatchClientInterface;
use IntegrationEngine\Core\Contract\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

final readonly class SymfonyHttpClientAdapter implements ClientAdapterInterface, BatchClientInterface
{
    use ResolvesAuthHeaders;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        /** @var array<string, string> */
        private array $defaultHeaders = [],
    ) {}

    public static function getClientType(): string
    {
        return 'rest';
    }

    public static function requiresPath(): bool
    {
        return true;
    }

    public static function requiresMethod(): bool
    {
        return true;
    }

    /**
     * @return array<mixed>
     *
     * @throws RequestResponseException on HTTP 4xx/5xx or network errors
     */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $path = $action->getPath($context);
        $method = $action->getMethod();

        try {
            $response = $this->httpClient->request(
                $method,
                $this->baseUrl.$path,
                $this->buildOptions($action, $headers),
            );

            return $this->consume($response, $method, $path);
        } catch (RequestResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->networkError($method, $path, $e);
        }
    }

    /**
     * Dispatches every request before consuming any response. Symfony
     * HttpClient responses are lazy, so the requests run concurrently and
     * total wall time approaches the slowest request instead of the sum.
     *
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    public function sendMany(array $requests): array
    {
        $dispatched = [];
        $results = [];

        foreach ($requests as $key => $request) {
            try {
                // Kept distinct from transport errors: path resolution
                // failures propagate their own exception type, as in send().
                $path = $request->action->getPath($request->context);
            } catch (\Throwable $e) {
                $results[$key] = $e;

                continue;
            }

            $method = $request->action->getMethod();

            try {
                $dispatched[$key] = [
                    $this->httpClient->request($method, $this->baseUrl.$path, $this->buildOptions($request->action, $request->headers)),
                    $method,
                    $path,
                ];
            } catch (\Throwable $e) {
                $results[$key] = $this->networkError($method, $path, $e);
            }
        }

        foreach ($dispatched as $key => [$response, $method, $path]) {
            try {
                $results[$key] = $this->consume($response, $method, $path);
            } catch (RequestResponseException $e) {
                $results[$key] = $e;
            } catch (\Throwable $e) {
                $results[$key] = $this->networkError($method, $path, $e);
            }
        }

        $ordered = [];

        foreach ($requests as $key => $request) {
            $ordered[$key] = $results[$key];
        }

        return $ordered;
    }

    /** @return array<string, mixed> */
    private function buildOptions(AbstractAction $action, ?RequestHeadersInterface $headers): array
    {
        $options = [
            'headers' => array_merge(
                $this->defaultAuthHeaders(),
                $this->defaultHeaders,
                $this->resolveHeaders($action),
                $headers?->toArray() ?? []
            ),
        ];

        $body = $action->getBody();
        if (null !== $body && \in_array($action->getMethod(), ['POST', 'PUT', 'PATCH'], strict: true)) {
            $options['json'] = $body->toArray();
        }

        return $options;
    }

    /**
     * @return array<mixed>
     *
     * @throws RequestResponseException on HTTP 4xx/5xx
     */
    private function consume(HttpResponseInterface $response, string $method, string $path): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            throw new RequestResponseException(
                statusCode: $statusCode,
                context: \sprintf(
                    '%s %s returned HTTP %d: %s',
                    $method,
                    $path,
                    $statusCode,
                    $response->getContent(throw: false)
                )
            );
        }

        $content = $response->getContent(throw: false);

        if (204 === $statusCode || '' === trim($content)) {
            return [];
        }

        return $response->toArray();
    }

    private function networkError(string $method, string $path, \Throwable $e): RequestResponseException
    {
        return new RequestResponseException(
            statusCode: 0,
            context: \sprintf(
                'Network error on %s %s: %s',
                $method,
                $path,
                $e->getMessage()
            )
        );
    }
}
