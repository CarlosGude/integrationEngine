<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

/**
 * Optional capability for clients that can target a different base URL
 * per request. When the integration's client implements it, the engine
 * honors an explicit baseUrl passed to send()/sendMany(); otherwise that
 * value is silently ignored and the client keeps using its configured URL.
 */
interface DynamicBaseUrlClientInterface
{
    /**
     * Returns a new instance pointed at the given base URL, without
     * mutating the original (client adapters are readonly).
     */
    public function withBaseUrl(string $baseUrl): static;
}
