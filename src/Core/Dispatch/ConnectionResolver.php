<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Dispatch;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Connection\ConnectionCredentials;
use IntegrationEngine\Core\Contract\Connection\ConnectionResolverInterface;
use IntegrationEngine\Core\Exception\ConnectionResolutionException;

final class ConnectionResolver
{
    public function __construct(
        private string $integrationName,
        private ?ConnectionResolverInterface $connectionResolver = null,
    ) {}

    /**
     * Resolves the connection (if any) and derives everything both send()
     * and sendMany() need to dispatch a request for it: the action with the
     * connection's authorization override applied, the base URL, and the
     * dynamic-auth cache discriminator.
     *
     * $connectionCache memoizes resolveConnection() across calls sharing
     * one scalar $connection — pass a variable from sendMany()'s loop so
     * repeated connections across batch items resolve only once; send()
     * doesn't pass one, since there's only ever one call to memoize.
     *
     * @param array<string, ?ConnectionCredentials> $connectionCache
     *
     * @return array{action: AbstractAction, baseUrl: ?string, cacheDiscriminator: ?string}
     */
    public function resolveForDispatch(
        AbstractAction $action,
        mixed $connection,
        ?string $baseUrl,
        array &$connectionCache = [],
    ): array {
        $credentials = $this->resolve($connection, $connectionCache);
        $action = $this->applyAuthorization($action, $credentials);

        $resolvedBaseUrl = $baseUrl ?? $credentials?->baseUrl;
        $cacheDiscriminator = $credentials->connectionId
            ?? (\is_scalar($connection) ? (string) $connection : null)
            ?? $resolvedBaseUrl;

        return [
            'action' => $action,
            'baseUrl' => $resolvedBaseUrl,
            'cacheDiscriminator' => $cacheDiscriminator,
        ];
    }

    /**
     * @param array<string, ?ConnectionCredentials> $connectionCache
     *
     * @throws ConnectionResolutionException when $connection is given but
     *                                       no connection_resolver is configured for this integration
     */
    private function resolve(mixed $connection, array &$connectionCache = []): ?ConnectionCredentials
    {
        if (null === $connection) {
            return null;
        }

        $cacheKey = \is_scalar($connection) ? (string) $connection : null;

        if (null !== $cacheKey && \array_key_exists($cacheKey, $connectionCache)) {
            return $connectionCache[$cacheKey];
        }

        if (null === $this->connectionResolver) {
            throw ConnectionResolutionException::noResolverConfigured($this->integrationName);
        }

        $credentials = $this->connectionResolver->resolve($connection);

        if (null !== $cacheKey) {
            $connectionCache[$cacheKey] = $credentials;
        }

        return $credentials;
    }

    private function applyAuthorization(AbstractAction $action, ?ConnectionCredentials $credentials): AbstractAction
    {
        if (null === $credentials?->authorization) {
            return $action;
        }

        return $action::create(
            method: $action->getMethod(),
            path: $action->getRawPath(),
            body: $action->getBody(),
            authorization: $credentials->authorization,
            cacheTtl: $action->getCacheTtl(),
        );
    }
}
