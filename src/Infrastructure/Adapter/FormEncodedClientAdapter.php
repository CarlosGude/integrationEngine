<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Adapter;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\ResolvesAuthHeaders;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

/**
 * HTTP client adapter for form-encoded requests.
 *
 * Handles `application/x-www-form-urlencoded` request bodies.
 * Used by APIs like Stripe, Twilio, Shopify that require form encoding.
 *
 * Example:
 * POST /payment_intents HTTP/1.1
 * Content-Type: application/x-www-form-urlencoded
 *
 * amount=2000&currency=usd&description=Widget
 */
final class FormEncodedClientAdapter implements ClientAdapterInterface
{
    use ResolvesAuthHeaders;

    public const CLIENT_TYPE = 'form_encoded';

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly array $defaultHeaders = [],
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
        return true;
    }

    public static function requiresMethod(): bool
    {
        return true;
    }

    /**
     * @throws RequestResponseException on HTTP 4xx/5xx or network errors
     *
     * @return array{body: array<mixed>, headers: array<string, list<string>>}
     */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $path = $action->getPath($context);
        $method = $action->getMethod();
        $options = $this->buildOptions($action, $headers);

        try {
            $response = $this->httpClient->request($method, $this->baseUrl.$path, $options);

            return $this->consume($response, $method, $path);
        } catch (RequestResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->networkError($method, $path, $e);
        }
    }

    /**
     * Build request options with form-encoded body.
     *
     * @return array{headers: array<string, string>, body?: string}
     */
    private function buildOptions(AbstractAction $action, ?RequestHeadersInterface $headers): array
    {
        $options = [
            'headers' => array_merge(
                $this->defaultAuthHeaders(),
                $this->defaultHeaders,
                $this->resolveHeaders($action),
                $headers?->toArray() ?? [],
            ),
        ];

        $body = $action->getBody();
        if ($body !== null && \in_array($action->getMethod(), ['POST', 'PUT', 'PATCH'], strict: true)) {
            $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $options['body'] = http_build_query($body->toArray());
        }

        return $options;
    }

    /**
     * Consume HTTP response and convert to standard format.
     *
     * @throws RequestResponseException on HTTP 4xx/5xx
     *
     * @return array{body: array<mixed>, headers: array<string, list<string>>}
     */
    private function consume(HttpResponseInterface $response, string $method, string $path): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            throw new RequestResponseException(
                statusCode: $statusCode,
                context: sprintf(
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

    /**
     * Wrap network error as RequestResponseException.
     */
    private function networkError(string $method, string $path, \Throwable $e): RequestResponseException
    {
        return new RequestResponseException(
            statusCode: 0,
            context: sprintf(
                'Network error on %s %s: %s',
                $method,
                $path,
                $e->getMessage(),
            ),
        );
    }
}
