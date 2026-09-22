<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Client\BodyEncoding;
use IntegrationEngine\Core\Contract\Client\Request;
use PHPUnit\Framework\TestCase;

final class RequestEncodingTest extends TestCase
{
    public function testPositionalConstructionDefaultsToJson(): void
    {
        $request = new Request('POST', 'https://example.com', [], ['id' => 1]);
        self::assertSame(BodyEncoding::Json, $request->bodyEncoding);
        self::assertNull($request->timeout);
    }

    public function testHeaderChangesPreserveEncodingBodyAndTimeout(): void
    {
        $request = new Request('POST', 'https://example.com', ['A' => 'b'], ['id' => 1], BodyEncoding::Form, 1.5);
        $changed = $request->withHeader('C', 'd');
        self::assertSame(BodyEncoding::Form, $changed->bodyEncoding);
        self::assertSame(['id' => 1], $changed->body);
        self::assertSame(1.5, $changed->timeout);
        self::assertSame(['A' => 'b', 'C' => 'd'], $changed->headers);
    }
}
