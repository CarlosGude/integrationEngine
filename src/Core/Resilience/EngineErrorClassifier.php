<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Resilience;

use IntegrationEngine\Core\Exception\RequestResponseException;

final class EngineErrorClassifier implements ErrorClassifierInterface
{
    public function classify(\Throwable $error): ErrorClassification
    {
        return new ErrorClassification($error instanceof RequestResponseException ? $error->statusCode : null);
    }
}
