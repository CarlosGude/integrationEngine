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

        $token = isset($auth->params['token']) && \is_string($auth->params['token']) ? $auth->params['token'] : '';
        $username = isset($auth->params['username']) && \is_string($auth->params['username']) ? $auth->params['username'] : '';
        $password = isset($auth->params['password']) && \is_string($auth->params['password']) ? $auth->params['password'] : '';
        $headerKey = isset($auth->params['header']) && \is_string($auth->params['header']) ? $auth->params['header'] : 'X-Api-Key';
        $prefix = isset($auth->params['prefix']) && \is_string($auth->params['prefix']) ? $auth->params['prefix'] : null;

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
}
