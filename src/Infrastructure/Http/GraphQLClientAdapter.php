<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GraphQLClientAdapter implements ClientAdapterInterface, DynamicBaseUrlClientInterface
{
    use ResolvesAuthHeaders;

    public const CLIENT_TYPE = 'graphql';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
        /** @var array<string, string> */
        private array $defaultHeaders = [],
    ) {}

    public function withBaseUrl(string $baseUrl): static
    {
        return new self($this->httpClient, $baseUrl, $this->defaultHeaders);
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
     * @return array<mixed>
     *
     * @throws RequestResponseException on HTTP errors or GraphQL errors in the response
     */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
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

        $options = [
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

        try {
            $response = $this->httpClient->request('POST', $this->endpointUrl, $options);

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

            // GraphQL always returns 200, even on errors.
            // Errors are signalled inside the response body under the "errors" key.
            $errors = $data['errors'] ?? null;
            if (!empty($errors) && \is_array($errors)) {
                $firstError = \is_array($errors[0]) ? $errors[0] : [];
                $message = \is_string($firstError['message'] ?? null)
                    ? $firstError['message']
                    : 'GraphQL error';

                throw new RequestResponseException(
                    statusCode: 200,
                    context: \sprintf(
                        'GraphQL error on action "%s": %s',
                        $action::getName(),
                        $message,
                    )
                );
            }

            $result = $data['data'] ?? [];

            return \is_array($result) ? $result : [];
        } catch (RequestResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RequestResponseException(
                statusCode: 0,
                context: \sprintf(
                    'Network error on GraphQL endpoint %s: %s',
                    $this->endpointUrl,
                    $e->getMessage()
                )
            );
        }
    }
}
