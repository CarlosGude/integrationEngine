<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;

final class FakeRequestMiddleware implements RequestMiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        return $next($request);
    }
}
