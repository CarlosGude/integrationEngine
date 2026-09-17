<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

/**
 * Generates webhook event DTO and request parser files.
 *
 * @author Carlos Gude
 */
final class WebhookFileGenerator
{
    /**
     * Generate webhook files.
     *
     * @return array<string, string> [filePath => content]
     */
    public function generateFiles(WebhookContext $ctx): array
    {
        $generationPath = $ctx->generationPath();

        return [
            $generationPath.'/'.$ctx->eventClassName().'.php' => $this->generateEventFile($ctx),
            $generationPath.'/'.$ctx->parserClassName().'.php' => $this->generateParserFile($ctx),
        ];
    }

    private function generateEventFile(WebhookContext $ctx): string
    {
        $namespace = $ctx->baseNamespace;
        $className = $ctx->eventClassName();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use IntegrationEngine\\Core\\Contract\\Webhook\\WebhookEventInterface;

/**
 * Webhook event DTO for {$ctx->integration}.{$ctx->event}.
 *
 * Represents the parsed and typed webhook payload from the {$ctx->integration} provider.
 */
final class {$className} implements WebhookEventInterface
{
    public function __construct(
        public readonly string \$id,
        public readonly string \$type,
    ) {}
}
PHP;
    }

    private function generateParserFile(WebhookContext $ctx): string
    {
        $namespace = $ctx->baseNamespace;
        $className = $ctx->parserClassName();
        $eventClassName = $ctx->eventClassName();
        $eventClassFqn = $ctx->eventClassFqn();
        $integration = $ctx->integration;
        $event = $ctx->event;
        $headerName = $ctx->headerName;
        $verifierType = $ctx->verifierType;

        $verifierSetup = $this->generateVerifierSetup($ctx);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use IntegrationEngine\\Core\\Contract\\Webhook\\AbstractWebhookMapper;
use IntegrationEngine\\Core\\Contract\\Webhook\\SignatureVerifierInterface;
use IntegrationEngine\\Infrastructure\\Webhook\\IntegrationWebhookRequestParser;
{$verifierSetup}

/**
 * Webhook request parser for {$integration}.{$event}.
 *
 * Extends IntegrationWebhookRequestParser to handle incoming webhook requests
 * from {$integration}, verify their signatures, and map to typed event DTOs.
 */
final readonly class {$className} extends IntegrationWebhookRequestParser
{
    public function __construct(
        private SignatureVerifierInterface \$verifier,
        private string \$secret,
    ) {}

    public function getDefinition(): string
    {
        return '{$event}';
    }

    public function getMapper(): AbstractWebhookMapper
    {
        return new {$eventClassName}Mapper();
    }

    protected function getSignatureVerifier(): SignatureVerifierInterface
    {
        return \$this->verifier;
    }

    protected function getSignatureSecret(): string
    {
        return \$this->secret;
    }
}

/**
 * Mapper for {$eventClassName}.
 *
 * Maps raw webhook payload to the typed event DTO.
 */
final class {$eventClassName}Mapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return '{$event}';
    }

    public function map(array \$payload, array \$headers): {$eventClassName}
    {
        return new {$eventClassName}(
            id: (string) (\$payload['id'] ?? ''),
            type: (string) (\$payload['type'] ?? '{$event}'),
        );
    }
}
PHP;
    }

    private function generateVerifierSetup(WebhookContext $ctx): string
    {
        return match ($ctx->verifierType) {
            'hmac_sha256' => 'use IntegrationEngine\\Core\\Webhook\\HmacSha256SignatureVerifier;',
            'timestamped_hmac' => 'use IntegrationEngine\\Core\\Webhook\\TimestampedHmacSignatureVerifier;',
            default => '',
        };
    }
}
