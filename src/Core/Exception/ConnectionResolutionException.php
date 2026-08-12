<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class ConnectionResolutionException extends \LogicException
{
    public static function noResolverConfigured(string $integrationName): self
    {
        return new self(\sprintf(
            'Integration "%s" received a $connection argument but has no connection_resolver configured.',
            $integrationName,
        ));
    }
}
