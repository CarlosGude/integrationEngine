<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\GraphQLBodyInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GraphQLClientAdapter implements ClientAdapterInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
        /** @var array<string, string> */
        private array $defaultHeaders = [],
    ) {}

    public static function getClientType(): string
    {
        return 'graphql';
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
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
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
            if (!empty($data['errors'])) {
                $message = \is_string($data['errors'][0]['message'] ?? null)
                    ? $data['errors'][0]['message']
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

            return $data['data'] ?? [];
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

    /** @return array<string, string> */
    private function resolveHeaders(AbstractAction $action): array
    {
        $headers = [];
        $auth = $action->getAuthorization();

        if (!$auth instanceof StaticAuthorizationConfig) {
            return $headers;
        }

        $token = isset($auth->params['token']) && \is_string($auth->params['token']) ? $auth->params['token'] : '';
        $username = isset($auth->params['username']) && \is_string($auth->params['username']) ? $auth->params['username'] : '';
        $password = isset($auth->params['password']) && \is_string($auth->params['password']) ? $auth->params['password'] : '';
        $headerKey = isset($auth->params['header']) && \is_string($auth->params['header']) ? $auth->params['header'] : 'X-Api-Key';

        $headers += match ($auth->type) {
            'bearer' => ['Authorization' => \sprintf('%s %s', $auth->params['prefix'] ?? 'Bearer', $token)],
            'basic' => ['Authorization' => \sprintf('Basic %s', base64_encode($username.':'.$password))],
            'api_key' => [$headerKey => $token],
            default => [],
        };

        return $headers;
    }
}
