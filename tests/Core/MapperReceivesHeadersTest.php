<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * End-to-end coverage that HTTP response headers reach the Mapper through
 * the full engine flow (client -> engine -> mapper), not just at the
 * adapter level.
 */
final class MapperReceivesHeadersTest extends IntegrationEngineTestCase
{
    #[Test]
    public function mapperReceivesBodyAndHeadersFromTheClientResponse(): void
    {
        $this->config->register(HeaderAwareAction::getName(), HeaderAwareAction::create('GET', '/items'));
        $this->client->setResponse(
            HeaderAwareAction::getName(),
            ['id' => 1],
            ['X-Request-Id' => ['abc123'], 'X-RateLimit-Remaining' => ['42']],
        );

        $response = $this->engine->send(HeaderAwareAction::getName());

        self::assertInstanceOf(HeaderAwareResponse::class, $response);
        self::assertSame(['id' => 1], $response->body);
        self::assertSame(['X-Request-Id' => ['abc123'], 'X-RateLimit-Remaining' => ['42']], $response->headers);
    }

    #[Test]
    public function mapperReceivesAnEmptyArrayWhenNoHeadersAreSet(): void
    {
        $this->config->register(HeaderAwareAction::getName(), HeaderAwareAction::create('GET', '/items'));
        $this->client->setResponse(HeaderAwareAction::getName(), ['id' => 1]);

        $response = $this->engine->send(HeaderAwareAction::getName());

        self::assertInstanceOf(HeaderAwareResponse::class, $response);
        self::assertSame([], $response->headers);
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class HeaderAwareResponse implements ResponseInterface
{
    /**
     * @param array<string, mixed>        $body
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public readonly array $body,
        public readonly array $headers,
    ) {}

    public function toArray(): array
    {
        return $this->body;
    }
}

final class HeaderAwareMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return HeaderAwareAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return new HeaderAwareResponse($response, $headers);
    }
}

final class HeaderAwareAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'header_aware_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return HeaderAwareMapper::class;
    }
}
