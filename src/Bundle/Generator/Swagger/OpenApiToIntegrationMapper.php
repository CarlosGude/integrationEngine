<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator\Swagger;

use IntegrationEngine\Bundle\Generator\IntegrationContext;

final class OpenApiToIntegrationMapper
{
    public function mapOperationToContext(
        IntegrationContext $base,
        SwaggerOperation $operation
    ): IntegrationContext {
        $action = ucfirst($operation->operationId);

        return $base->with(
            action: $action,
            method: $operation->method,
            path: $operation->path,
            requestSchema: $operation->requestBody,
            responseSchema: $operation->responses,
        );
    }
}
