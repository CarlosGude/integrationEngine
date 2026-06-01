<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator\Swagger;

final class SwaggerSpec
{
    /** @param SwaggerOperation[] $operations */
    public function __construct(
        public readonly array $operations,
    ) {}

    public static function fromArray(array $data): self
    {
        $operations = [];

        foreach ($data['paths'] ?? [] as $path => $methods) {

            foreach ($methods as $method => $operation) {

                if (!is_array($operation)) {
                    continue;
                }

                $operations[] = new SwaggerOperation(
                    operationId: $operation['operationId'] ?? ucfirst($method) . md5($path),
                    method: strtoupper($method),
                    path: $path,
                    requestBody: $operation['requestBody']['content']['application/json']['schema'] ?? [],
                    responses: $operation['responses'] ?? [],
                );
            }
        }

        return new self($operations);
    }
}
