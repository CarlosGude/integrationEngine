<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Client;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;

/**
 * Runs every outgoing request through an ordered list of middlewares before
 * reaching the inner HTTP adapter. Middleware[0] is outermost (first to
 * process, last to return). Always implements BatchClientInterface: if the
 * inner adapter supports concurrent batches, they run concurrently; otherwise
 * the batch falls back to sequential per-item sends.
 */
final class MiddlewareClient implements ClientInterface, BatchClientInterface, DynamicBaseUrlClientInterface
{
    /** @param list<AbstractClientMiddleware> $middlewares */
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly array $middlewares,
    ) {}

    public function withBaseUrl(string $baseUrl): static
    {
        if (!$this->inner instanceof DynamicBaseUrlClientInterface) {
            return $this;
        }

        return new self($this->inner->withBaseUrl($baseUrl), $this->middlewares);
    }

    /** @return array<mixed> */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $next = fn (AbstractAction $a, ?ActionContextInterface $c, ?RequestHeadersInterface $h): array => $this->inner->send($a, $c, $h);

        foreach (array_reverse($this->middlewares) as $middleware) {
            $current = $next;
            $next = static fn (AbstractAction $a, ?ActionContextInterface $c, ?RequestHeadersInterface $h): array => $middleware->process($a, $c, $h, $current);
        }

        return $next($action, $context, $headers);
    }

    /**
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    public function sendMany(array $requests): array
    {
        $next = $this->dispatchBatch(...);

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = $this->wrapBatch($middleware, $next);
        }

        return $next($requests);
    }

    /**
     * @param callable(array<array-key, PreparedRequest>): array<array-key, array<mixed>|\Throwable> $next
     *
     * @return callable(array<array-key, PreparedRequest>): array<array-key, array<mixed>|\Throwable>
     */
    private function wrapBatch(AbstractClientMiddleware $middleware, callable $next): callable
    {
        return new class($middleware, $next) {
            /** @var callable(array<array-key, PreparedRequest>): array<array-key, array<mixed>|\Throwable> */
            private $next;

            public function __construct(
                private readonly AbstractClientMiddleware $middleware,
                callable $next,
            ) {
                $this->next = $next;
            }

            /**
             * @param array<array-key, PreparedRequest> $requests
             *
             * @return array<array-key, array<mixed>|\Throwable>
             */
            public function __invoke(array $requests): array
            {
                return $this->middleware->processMany($requests, $this->next);
            }
        };
    }

    /**
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    private function dispatchBatch(array $requests): array
    {
        if ($this->inner instanceof BatchClientInterface) {
            return $this->inner->sendMany($requests);
        }

        return $this->sequentialFallback($requests);
    }

    /**
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    private function sequentialFallback(array $requests): array
    {
        $results = [];

        foreach ($requests as $key => $request) {
            try {
                $results[$key] = $this->inner->send($request->action, $request->context, $request->headers);
            } catch (\Throwable $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }
}
