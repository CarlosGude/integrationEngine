<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;

trait RunsRequestMiddlewares
{
    /**
     * Runs $request through $requestMiddlewares (outermost first, same
     * convention as the action-level `middlewares:` config) before calling
     * $terminal — the actual HTTP dispatch. With none configured — the
     * common case — this reduces to a direct call to $terminal, no
     * behavior change from before request middlewares existed.
     *
     * @param list<RequestMiddlewareInterface>                                                                     $requestMiddlewares
     * @param callable(Request): array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int} $terminal
     *
     * @return array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int}
     */
    private function dispatchThroughRequestMiddlewares(Request $request, array $requestMiddlewares, callable $terminal): array
    {
        $chain = $terminal;

        foreach (array_reverse($requestMiddlewares) as $middleware) {
            $next = $chain;
            $chain = static fn (Request $r): array => $middleware->handle($r, $next);
        }

        return $chain($request);
    }
}
