<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Debug;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;

/**
 * Decorates ClientInterface to record every outgoing call in the profiler
 * collector. Wraps the request HTTP details actually sent over the wire —
 * it does not see the Action's logical name from inside IntegrationEngine,
 * only what AbstractAction itself exposes (getName(), getMethod(), getRawPath()).
 *
 * Only wired by IntegrationCompilerPass when kernel.debug is true — the
 * decorated client is used as-is in any other environment.
 */
final class TraceableClient implements ClientInterface, DynamicBaseUrlClientInterface
{
    public function __construct(
        private readonly ClientInterface $decorated,
        private readonly string $integrationName,
        private readonly IntegrationEngineDataCollector $collector,
    ) {}

    public function withBaseUrl(string $baseUrl): static
    {
        if (!$this->decorated instanceof DynamicBaseUrlClientInterface) {
            return $this;
        }

        return new self(
            $this->decorated->withBaseUrl($baseUrl),
            $this->integrationName,
            $this->collector,
        );
    }

    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $start = microtime(true);
        $error = null;

        try {
            return $this->decorated->send($action, $context, $headers);
        } catch (\Throwable $e) {
            $error = $e;

            throw $e;
        } finally {
            $this->collector->recordCall(
                integrationName: $this->integrationName,
                actionName: $action::getName(),
                method: $action->getMethod(),
                path: $action->getRawPath(),
                durationMs: (microtime(true) - $start) * 1000,
                error: $error,
                statusCode: $error instanceof RequestResponseException ? $error->statusCode : null,
            );
        }
    }
}
