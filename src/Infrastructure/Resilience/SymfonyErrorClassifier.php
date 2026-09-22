<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Resilience;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\Resilience\ErrorClassification;
use IntegrationEngine\Core\Resilience\ErrorClassifierInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class SymfonyErrorClassifier implements ErrorClassifierInterface
{
    public function classify(\Throwable $error): ErrorClassification
    {
        if ($error instanceof RequestResponseException) {
            return new ErrorClassification($error->statusCode);
        }

        return new ErrorClassification(
            statusCode: $error instanceof HttpExceptionInterface ? $error->getResponse()->getStatusCode() : null,
            networkError: $error instanceof TransportExceptionInterface,
        );
    }
}
