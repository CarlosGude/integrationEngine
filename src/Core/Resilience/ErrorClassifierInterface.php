<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Resilience;

interface ErrorClassifierInterface
{
    public function classify(\Throwable $error): ErrorClassification;
}
