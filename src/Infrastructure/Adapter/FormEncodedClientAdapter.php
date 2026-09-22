<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Adapter;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\BodyEncoding;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
final class FormEncodedClientAdapter implements ClientAdapterInterface, DynamicBaseUrlClientInterface, BatchClientInterface
{
    public const CLIENT_TYPE = 'form_encoded';

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly array $defaultHeaders = [],
        /** @var list<RequestMiddlewareInterface> */
        private readonly array $requestMiddlewares = [],
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

    /** @return array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int} */
    public function send(AbstractAction $action, ?ActionContextInterface $context = null, ?RequestHeadersInterface $headers = null): array
    {
        return $this->transport()->send($action, $context, $headers);
    }

    /**
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int}|\Throwable>
     */
    public function sendMany(array $requests): array
    {
        return $this->transport()->sendMany($requests);
    }

    private function transport(): SymfonyHttpClientAdapter
    {
        return new SymfonyHttpClientAdapter($this->httpClient, $this->baseUrl, $this->defaultHeaders, $this->requestMiddlewares, BodyEncoding::Form);
    }
}
