<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class FakeTokenResponse implements ResponseInterface
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
