<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

final readonly class GraphQLClientAdapter implements ClientAdapterInterface, BatchClientInterface, DynamicBaseUrlClientInterface
{
    use ResolvesAuthHeaders;
    use RunsRequestMiddlewares;

    public const CLIENT_TYPE = 'graphql';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
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
        return false;
    }

    public static function requiresMethod(): bool
    {
        return false;
    }

    /**
     * @return array{body: array<mixed>, headers: array<string, list<string>>}
     *
     * @throws RequestResponseException on HTTP errors or GraphQL errors in the response
     */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $options = $this->buildOptions($action, $headers);
        $request = new Request('POST', $this->endpointUrl, $options['headers'], $options['json']);
        $actionName = $action::getName();

        return $this->dispatchThroughRequestMiddlewares(
            $request,
            $this->requestMiddlewares,
            fn (Request $r): array => $this->execute($r, $actionName),
        );
    }

    /**
     * Dispatches every request before consuming any response, mirroring
     * SymfonyHttpClientAdapter::sendMany() so GraphQL batches get the same
     * concurrency (Symfony HttpClient responses are lazy regardless of
     * REST vs GraphQL — both are plain HTTP requests under the hood).
     *
     * Falls back to sequential per-item send() whenever request_middlewares
     * are configured — see SymfonyHttpClientAdapter::sendMany() for why.
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

        /** @var array<array-key, HttpResponseInterface> $dispatched */
        $dispatched = [];
        $results = [];

        foreach ($requests as $key => $request) {
            try {

                // option building (incl. auth header resolution) are
                // configuration concerns and propagate their own exception
                // type raw, exactly as in send().
                $options = $this->buildOptions($request->action, $request->headers);
            } catch (\Throwable $e) {
                $results[$key] = $e;

                continue;
            }

            try {
                $dispatched[$key] = $this->httpClient->request('POST', $this->endpointUrl, $options);
            } catch (\Throwable $e) {
                $results[$key] = $this->networkError($e);
            }
        }

        foreach ($dispatched as $key => $response) {
            try {
                $results[$key] = $this->parseResponse($response, $requests[$key]->action::getName());
            } catch (RequestResponseException $e) {
                $results[$key] = $e;
            } catch (\Throwable $e) {
                $results[$key] = $this->networkError($e);
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
     * @throws RequestResponseException on HTTP errors, GraphQL errors, or network errors
     */
    private function execute(Request $request, string $actionName): array
    {
        try {
            $response = $this->httpClient->request($request->method, $request->url, [
                'headers' => $request->headers,
                'json' => $request->body,
            ]);

            return $this->parseResponse($response, $actionName);
        } catch (RequestResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->networkError($e);
        }
    }

    /**
     * @return array{headers: array<string, string>, json: array<string, mixed>}
     *
     * @throws RequestResponseException when the action's body isn't a GraphQLBodyInterface
     */
    private function buildOptions(AbstractAction $action, ?RequestHeadersInterface $headers): array
    {
        $body = $action->getBody();

        if (!$body instanceof GraphQLBodyInterface) {
            throw new RequestResponseException(
                statusCode: 0,
                context: \sprintf(
                    'GraphQLClientAdapter requires a GraphQLBodyInterface body. Got %s for action "%s".',
                    null !== $body ? $body::class : 'null',
                    $action::getName(),
                )
            );
        }

        return [
            'headers' => array_merge(
                ['Content-Type' => 'application/json'],
                $this->defaultAuthHeaders(),
                $this->defaultHeaders,
                $this->resolveHeaders($action),
                $headers?->toArray() ?? [],
            ),
            'json' => [
                'query' => $body->getQuery(),
                'variables' => $body->getVariables(),
            ],
        ];
    }

    /**
     * @return array{body: array<mixed>, headers: array<string, list<string>>}
     *
     * @throws RequestResponseException on HTTP errors or GraphQL errors in the response
     */
    private function parseResponse(HttpResponseInterface $response, string $actionName): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            throw new RequestResponseException(
                statusCode: $statusCode,
                context: \sprintf(
                    'GraphQL endpoint %s returned HTTP %d: %s',
                    $this->endpointUrl,
                    $statusCode,
                    $response->getContent(throw: false)
                )
            );
        }

        $data = $response->toArray();



        $errors = $data['errors'] ?? null;
        if (!empty($errors) && \is_array($errors)) {
            $firstError = isset($errors[0]) && \is_array($errors[0]) ? $errors[0] : [];
            $message = \is_string($firstError['message'] ?? null)
                ? $firstError['message']
                : 'GraphQL error';

            throw new RequestResponseException(
                statusCode: 200,
                context: \sprintf(
                    'GraphQL error on action "%s": %s',
                    $actionName,
                    $message,
                )
            );
        }

        $result = $data['data'] ?? [];

        return ['body' => \is_array($result) ? $result : [], 'headers' => $response->getHeaders(throw: false)];
    }

    private function networkError(\Throwable $e): RequestResponseException
    {
        return new RequestResponseException(
            statusCode: 0,
            context: \sprintf(
                'Network error on GraphQL endpoint %s: %s',
                $this->endpointUrl,
                $e->getMessage()
            )
        );
    }

    /**
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array{body: array<mixed>, headers: array<string, list<string>>}|\Throwable>
     */
    private function sendManySequentially(array $requests): array
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
