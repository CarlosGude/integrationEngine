<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

interface SignatureVerifierInterface
{
    /** @param array<string, list<string>> $headers */
    public function verify(string $rawBody, array $headers, SignatureConfig $config): void;
}
