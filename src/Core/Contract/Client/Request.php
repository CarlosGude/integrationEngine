<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

/**
 * A fully-built outgoing HTTP request — method, resolved URL, headers and
 * body — ready to hand to the transport. This is what RequestMiddlewareInterface
 * inspects/modifies; unlike AbstractClientMiddleware (which sees the action
 * before path placeholders and body are resolved), everything a signature
 * scheme like OAuth 1.0a needs is already present here.
 */
final readonly class Request
{
    /**
     * @param array<string, string>     $headers
     * @param null|array<string, mixed> $body    null when the request has no
     *                                           payload (e.g. GET) — distinct from an empty payload
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers,
        public ?array $body = null,
        public BodyEncoding $bodyEncoding = BodyEncoding::Json,
        public ?float $timeout = null,
    ) {}

    public function withHeader(string $name, string $value): self
    {
        return new self($this->method, $this->url, [...$this->headers, $name => $value], $this->body, $this->bodyEncoding, $this->timeout);
    }
}
