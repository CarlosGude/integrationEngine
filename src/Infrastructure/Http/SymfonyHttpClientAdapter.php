<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SymfonyHttpClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string  { return 'rest'; }
    public static function requiresPath(): bool     { return true; }
    public static function requiresMethod(): bool   { return true; }
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        /** @var array<string, string> */
        private array $defaultHeaders = [],
    ) {}

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

        $options = [
            'headers' => array_merge(
                $this->defaultHeaders,
                $this->resolveHeaders($action),
                $headers?->toArray() ?? []
            ),
        ];

        $body = $action->getBody();
        if (null !== $body && \in_array($action->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], strict: true)) {
            $options['json'] = $body->toArray();
        }

        try {
            $response = $this->httpClient->request(
                $action->getMethod(),
                $this->baseUrl.$path,
                $options,
            );

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                throw new RequestResponseException(
                    statusCode: $statusCode,
                    context: \sprintf(
                        '%s %s returned HTTP %d: %s',
                        $action->getMethod(),
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
        } catch (RequestResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RequestResponseException(
                statusCode: 0,
                context: \sprintf(
                    'Network error on %s %s: %s',
                    $action->getMethod(),
                    $path,
                    $e->getMessage()
                )
            );
        }
    }

    /** @return array<string, string> */
    private function resolveHeaders(AbstractAction $action): array
    {
        $headers = ['Accept' => 'application/json'];

        $auth = $action->getAuthorization();

        if (!$auth instanceof StaticAuthorizationConfig) {
            return $headers;
        }

        $token = isset($auth->params['token']) && \is_string($auth->params['token']) ? $auth->params['token'] : '';
        $username = isset($auth->params['username']) && \is_string($auth->params['username']) ? $auth->params['username'] : '';
        $password = isset($auth->params['password']) && \is_string($auth->params['password']) ? $auth->params['password'] : '';
        $headerKey = isset($auth->params['header']) && \is_string($auth->params['header']) ? $auth->params['header'] : 'X-Api-Key';

        $headers += match ($auth->type) {
            'bearer' => ['Authorization' => \sprintf('Bearer %s', $token)],
            'basic' => ['Authorization' => \sprintf('Basic %s', base64_encode($username.':'.$password))],
            'api_key' => [$headerKey => $token],
            default => [],
        };

        return $headers;
    }
}