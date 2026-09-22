<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Security;

use IntegrationEngine\Core\Exception\DisallowedHostException;

final readonly class HostPolicy
{
    /** @param list<string> $allowedHosts */
    public function __construct(private array $allowedHosts = []) {}

    public function assertAllowed(string $url): void
    {
        if ([] === $this->allowedHosts) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (\is_string($host) && \is_string($scheme) && \in_array(strtolower($scheme), ['http', 'https'], true)) {
            $host = strtolower($host);
            foreach ($this->allowedHosts as $pattern) {
                $pattern = strtolower($pattern);
                if ($host === $pattern || (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1)) && \strlen($host) > \strlen($pattern) - 1)) {
                    return;
                }
            }
        }

        throw new DisallowedHostException();
    }
}
