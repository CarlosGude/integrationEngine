<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

final readonly class SymfonyHttpClientAdapter extends HttpClientAdapterBase implements BatchClientInterface, DynamicBaseUrlClientInterface
{
    use ResolvesAuthHeaders;
    use RunsRequestMiddlewares;

    public const CLIENT_TYPE = 'rest';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        /** @var array<string, string> */
        private array $defaultHeaders = [],
        /** @var list<RequestMiddlewareInterface> */
        private array $requestMiddlewares = [],
    ) {}

    public function withBaseUrl(string $baseUrl): static
    {
        return new self($this->httpClient, $baseUrl, $this->defaultHeaders, $this->requestMiddlewares);
    }

    public static function getClientType(): string
    {
        return self::CLIENT_TYPE;
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
     * @return array{body: array<mixed>, headers: array<string, list<string>>}
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
        $options = $this->buildOptions($action, $headers);

        $request = new Request($method, $this->baseUrl.$path, $options['headers'], $options['json'] ?? null);

        return $this->dispatchThroughRequestMiddlewares(
            $request,
            $this->requestMiddlewares,
            fn (Request $r): array => $this->execute($r, $path),
        );
    }

    /**
     * Dispatches every request before consuming any response. Symfony
     * HttpClient responses are lazy, so the requests run concurrently and
     * total wall time approaches the slowest request instead of the sum.
     *
     * Falls back to sequential per-item send() whenever request_middlewares
     * are configured: a middleware may need to observe/short-circuit each
     * response individually (see RequestMiddlewareInterface), which the
     * dispatch-all-then-consume-all concurrency below can't accommodate.
     * This only affects integrations that opt into request middlewares.
     *
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array{body: array<mixed>, headers: array<string, list<string>>}|\Throwable>
     */
    public function sendMany(array $requests): array
    {
        if ([] !== $this->requestMiddlewares) {
            return $this->sendManySequentially($requests);
        }

        /** @var array<array-key, DispatchedRequest> $dispatched */
        $dispatched = [];
        $results = [];

        foreach ($requests as $key => $request) {
            try {
                // Kept distinct from transport errors: path resolution and
                // option building (incl. auth header resolution) are
                // configuration concerns and propagate their own exception
                // type raw, exactly as in send() where they sit outside the
                // network try/catch below.
                $path = $request->action->getPath($request->context);
                $options = $this->buildOptions($request->action, $request->headers);
            } catch (\Throwable $e) {
                $results[$key] = $e;

                continue;
            }

            $method = $request->action->getMethod();

            try {
                $dispatched[$key] = new DispatchedRequest(
                    $this->httpClient->request($method, $this->baseUrl.$path, $options),
                    $method,
                    $path,
                );
            } catch (\Throwable $e) {
                $results[$key] = $this->networkError($method, $path, $e);
            }
        }

        foreach ($dispatched as $key => $item) {
            try {
                $results[$key] = $this->consume($item->response, $item->method, $item->path);
            } catch (RequestResponseException $e) {
                $results[$key] = $e;
            } catch (\Throwable $e) {
                $results[$key] = $this->networkError($item->method, $item->path, $e);
            }
        }

        $ordered = [];

        foreach ($requests as $key => $request) {
            $ordered[$key] = $results[$key];
        }

        return $ordered;
    }

    /**
     * @return array{body: array<mixed>, headers: array<string, list<string>>}
     *
     * @throws RequestResponseException on HTTP 4xx/5xx or network errors
     */
    private function execute(Request $request, string $path): array
    {
        try {
            $options = ['headers' => $request->headers];
            if (null !== $request->body) {
                $options['json'] = $request->body;
            }

            $response = $this->httpClient->request($request->method, $request->url, $options);

            return $this->consume($response, $request->method, $path);
        } catch (RequestResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->networkError($request->method, $path, $e);
        }
    }

    /** @return array{headers: array<string, string>, json?: array<string, mixed>} */
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
     * @return array{body: array<mixed>, headers: array<string, list<string>>}
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
        $body = (204 === $statusCode || '' === trim($content)) ? [] : $response->toArray();

        return ['body' => $body, 'headers' => $response->getHeaders(throw: false)];
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
