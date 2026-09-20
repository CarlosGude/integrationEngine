<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

/**
 * Generates webhook event DTO, mapper and request parser files.
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
            $generationPath.'/'.$ctx->mapperClassName().'.php' => $this->generateMapperFile($ctx),
            $generationPath.'/'.$ctx->parserClassName().'.php' => $this->generateParserFile($ctx),
            $generationPath.'/'.$ctx->consumerClassName().'.php' => $this->generateConsumerFile($ctx),
        ];
    }

    private function generateConsumerFile(WebhookContext $ctx): string
    {
        $namespace = $ctx->namespace();
        $className = $ctx->consumerClassName();
        $mapperClassName = $ctx->mapperClassName();
        $routingKey = $ctx->routingKey();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use IntegrationEngine\\Infrastructure\\Webhook\\WebhookEventDispatcher;
use Symfony\\Component\\RemoteEvent\\Attribute\\AsRemoteEventConsumer;
use Symfony\\Component\\RemoteEvent\\Consumer\\ConsumerInterface;
use Symfony\\Component\\RemoteEvent\\RemoteEvent;

/**
 * Consumes the verified {$ctx->integration}.{$ctx->event} webhook.
 *
 * Symfony hands the RemoteEvent to this consumer through Messenger, keyed by
 * the routing name below. Map here, and put the domain work in a listener of
 * the typed event.
 *
 * Register the matching route:
 *
 *     framework:
 *         webhook:
 *             routing:
 *                 {$routingKey}:
 *                     service: {$ctx->parserClassFqn()}
 *                     secret: '%env(WEBHOOK_SECRET)%'
 */
#[AsRemoteEventConsumer('{$routingKey}')]
final readonly class {$className} implements ConsumerInterface
{
    public function __construct(
        private WebhookEventDispatcher \$dispatcher,
    ) {}

    public function consume(RemoteEvent \$event): void
    {
        \$mapper = new {$mapperClassName}();

        // Every event parsed at this URL is named after the parser's
        // getDefinition(), so a provider posting several event types to the
        // same URL has to be filtered here. Drop this guard when the payload
        // carries no "type" (Shopify, for one, sends it as a header).
        if ((\$event->getPayload()['type'] ?? null) !== \$mapper->getDefinition()) {
            return;
        }

        \$this->dispatcher->dispatch(\$event, \$mapper, []);
    }
}
PHP;
    }

    private function generateEventFile(WebhookContext $ctx): string
    {
        $namespace = $ctx->namespace();
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
        $namespace = $ctx->namespace();
        $className = $ctx->parserClassName();
        $mapperClassName = $ctx->mapperClassName();
        $integration = $ctx->integration;
        $event = $ctx->event;

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
final class {$className} extends IntegrationWebhookRequestParser
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
        return new {$mapperClassName}();
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
PHP;
    }

    private function generateMapperFile(WebhookContext $ctx): string
    {
        $namespace = $ctx->namespace();
        $className = $ctx->mapperClassName();
        $eventClassName = $ctx->eventClassName();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use IntegrationEngine\\Core\\Contract\\Webhook\\AbstractWebhookMapper;

/**
 * Maps the raw {$ctx->integration}.{$ctx->event} payload to {$eventClassName}.
 */
final class {$className} extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return '{$ctx->event}';
    }

    public function map(array \$payload, array \$headers): {$eventClassName}
    {
        return new {$eventClassName}(
            id: (string) (\$payload['id'] ?? ''),
            type: (string) (\$payload['type'] ?? '{$ctx->event}'),
        );
    }
}
PHP;
    }

    private function generateVerifierSetup(WebhookContext $ctx): string
    {
        return match ($ctx->verifierType) {
            'hmac_sha256' => 'use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;',
            'timestamped_hmac' => 'use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;',
            default => '',
        };
    }
}
