<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Client\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    #[Test]
    public function withHeaderAddsTheNewHeaderAndKeepsTheExistingOnes(): void
    {
        $request = new Request('GET', 'https://api.example.com/orders', ['X-Existing' => 'orig'], ['id' => 1]);

        $withHeader = $request->withHeader('X-New', 'value');

        self::assertSame(['X-Existing' => 'orig', 'X-New' => 'value'], $withHeader->headers);
        self::assertSame(['id' => 1], $withHeader->body);
        self::assertSame('GET', $withHeader->method);
        self::assertSame('https://api.example.com/orders', $withHeader->url);
    }

    #[Test]
    public function withHeaderDoesNotMutateTheOriginalRequest(): void
    {
        $request = new Request('GET', 'https://api.example.com/orders', ['X-Existing' => 'orig']);

        $request->withHeader('X-New', 'value');

        self::assertSame(['X-Existing' => 'orig'], $request->headers);
    }
}
