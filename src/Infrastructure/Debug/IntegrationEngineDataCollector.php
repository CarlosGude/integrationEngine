<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Debug;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Collects every outgoing call made through TracingMiddleware (and cache hits
 * recorded by CachingMiddleware) during one incoming HTTP request, for display
 * in the Symfony profiler.
 *
 * One instance lives for the lifetime of a single app request — calls are
 * recorded eagerly via recordCall() as each middleware executes, not gathered
 * at collect() time, since the engine has no single point where every
 * outgoing call funnels through before the response is built.
 *
 * Excluded from the bundle's blanket service autodiscovery (see services.yaml)
 * because DataCollectorInterface comes from symfony/http-kernel, which is not
 * a required dependency of this bundle — only registered by IntegrationCompilerPass
 * when that interface is actually available.
 */
final class IntegrationEngineDataCollector implements DataCollectorInterface
{
    /** @var list<IntegrationCall> */
    private array $calls = [];

    public function recordCall(
        string $integrationName,
        string $actionName,
        string $method,
        string $path,
        float $durationMs,
        ?\Throwable $error,
        ?int $statusCode = null,
        bool $cached = false,
    ): void {
        $this->calls[] = new IntegrationCall(
            integrationName: $integrationName,
            actionName: $actionName,
            method: $method,
            path: $path,
            durationMs: $durationMs,
            error: $error?->getMessage(),
            statusCode: $statusCode,
            cached: $cached,
        );
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // No-op: calls are recorded as they happen via recordCall(), not
        // gathered here — nothing left to collect at this point.
    }

    public function reset(): void
    {
        $this->calls = [];
    }

    /** @return list<IntegrationCall> */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getTotalCalls(): int
    {
        return \count($this->calls);
    }

    public function getTotalDurationMs(): float
    {
        return array_sum(array_map(
            static fn (IntegrationCall $call): float => $call->durationMs,
            $this->calls,
        ));
    }

    public function getErrorCount(): int
    {
        return \count(array_filter(
            $this->calls,
            static fn (IntegrationCall $call): bool => null !== $call->error,
        ));
    }

    public function getCachedCount(): int
    {
        return \count(array_filter(
            $this->calls,
            static fn (IntegrationCall $call): bool => $call->cached,
        ));
    }

    public function getName(): string
    {
        return 'integration_engine';
    }
}
