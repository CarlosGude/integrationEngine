<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;

trait ResolvesAuthHeaders
{
    /**
     * Builds the Authorization / API-key headers from the action's static auth config.
     *
     * @return array<string, string>
     */
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

    /**
     * @return array|string[]
     */
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

        return match ($auth->type) {
            'bearer' => ['Authorization' => \sprintf('%s %s', $auth->params['prefix'] ?? 'Bearer', $token)],
            'basic' => ['Authorization' => \sprintf('Basic %s', base64_encode($username.':'.$password))],
            'api_key' => [$headerKey => $token],
            default => [],
        };
    }
}
