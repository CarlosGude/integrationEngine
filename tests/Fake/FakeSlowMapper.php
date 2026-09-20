<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

/**
 * Takes measurable time to map, so the mapping duration carried by
 * ResponseMapped can be asserted against a real elapsed time.
 */
final class FakeSlowMapper extends AbstractMapper
{
    public const DELAY_MICROSECONDS = 20_000;

    public static function getAction(): string
    {
        return FakeSlowAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        usleep(self::DELAY_MICROSECONDS);

        /** @var array<string, mixed> $response */
        return new FakeTokenResponse($response);
    }
}
