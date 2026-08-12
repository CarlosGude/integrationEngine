<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Auth\StaticAuthorizationConfig;

trait ResolvesAuthHeaders
{
    /**
     * Base headers merged at the lowest priority before constructor defaults and auth headers.
     * Override in the using class to set adapter-specific defaults.
     *
     * @return array<string, string>
     */
    protected function defaultAuthHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    /** @return array<string, string> */
    private function resolveHeaders(AbstractAction $action): array
    {
        $auth = $action->getAuthorization();

        if (!$auth instanceof StaticAuthorizationConfig) {
            return [];
        }

        $token = $this->stringParam($auth->params, 'token', '');
        $username = $this->stringParam($auth->params, 'username', '');
        $password = $this->stringParam($auth->params, 'password', '');
        $headerKey = $this->stringParam($auth->params, 'header', 'X-Api-Key');
        $prefix = $this->nullableStringParam($auth->params, 'prefix');

        return match ($auth->type) {
            'bearer' => ['Authorization' => \sprintf('%s %s', $prefix ?? 'Bearer', $token)],
            'basic' => ['Authorization' => \sprintf('Basic %s', base64_encode($username.':'.$password))],
            // api_key carries the bare token unless a prefix is explicitly set.
            'api_key' => [$headerKey => null !== $prefix && '' !== $prefix ? \sprintf('%s %s', $prefix, $token) : $token],
            default => throw new \InvalidArgumentException(\sprintf(
                'Unknown static authorization type "%s". Supported types: bearer, basic, api_key.',
                $auth->type,
            )),
        };
    }

    /**
     * Reads a string-typed auth param. A missing key falls back to $default
     * (many params are optional per auth type); a key present with a
     * non-string value fails loudly instead of silently degrading to an
     * empty credential.
     *
     * @param array<string, mixed> $params
     */
    private function stringParam(array $params, string $key, string $default): string
    {
        return $this->nullableStringParam($params, $key) ?? $default;
    }

    /**
     * Same as stringParam(), but a missing key returns null instead of a
     * default — for params where "not set" and "set to empty string" are
     * meaningfully different (e.g. prefix).
     *
     * @param array<string, mixed> $params
     */
    private function nullableStringParam(array $params, string $key): ?string
    {
        if (!\array_key_exists($key, $params)) {
            return null;
        }

        if (!\is_string($params[$key])) {
            throw new \InvalidArgumentException(\sprintf(
                'Static authorization param "%s" must be a string, got %s.',
                $key,
                get_debug_type($params[$key]),
            ));
        }

        return $params[$key];
    }
}
