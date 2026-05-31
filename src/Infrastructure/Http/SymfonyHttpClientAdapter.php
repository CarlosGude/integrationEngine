<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyHttpClientAdapter implements ClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @throws RequestResponseException on HTTP 4xx/5xx or network errors
     */
    public function send(AbstractAction $action): array
    {
        $options = [
            'headers' => $this->resolveHeaders($action),
        ];

        $body = $action->getBody();
        if ($body !== null && in_array($action->getMethod(), ['POST', 'PUT', 'PATCH'], strict: true)) {
            $options['json'] = $body->toArray();
        }

        try {
            $response = $this->httpClient->request(
                $action->getMethod(),
                $this->baseUrl . $action->getPath(),
                $options,
            );

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                throw new RequestResponseException(
                    statusCode: $statusCode,
                    context: sprintf(
                        '%s %s returned HTTP %d: %s',
                        $action->getMethod(),
                        $action->getPath(),
                        $statusCode,
                        $response->getContent(throw: false),
                    ),
                );
            }

            return $response->toArray();
        } catch (RequestResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RequestResponseException(
                statusCode: 0,
                context: sprintf(
                    'Network error on %s %s: %s',
                    $action->getMethod(),
                    $action->getPath(),
                    $e->getMessage(),
                ),
            );
        }
    }

    private function resolveHeaders(AbstractAction $action): array
    {
        $headers = ['Accept' => 'application/json'];

        $auth = $action->getAuthorization();

        if (!$auth instanceof StaticAuthorizationConfig) {
            return $headers;
        }

        $headers += match ($auth->type) {
            'bearer'  => ['Authorization' => sprintf('Bearer %s', $auth->params['token'] ?? '')],
            'basic'   => ['Authorization' => sprintf(
                'Basic %s',
                base64_encode(($auth->params['username'] ?? '') . ':' . ($auth->params['password'] ?? ''))
            )],
            'api_key' => [($auth->params['header'] ?? 'X-Api-Key') => $auth->params['token'] ?? ''],
            default   => [],
        };

        return $headers;
    }
}
