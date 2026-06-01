<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator\Swagger;

final class SwaggerOperation
{
    public function __construct(
        public readonly string $operationId,
        public readonly string $method,
        public readonly string $path,
        public readonly array $requestBody,
        public readonly array $responses,
    ) {}
}
