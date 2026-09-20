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

use IntegrationEngine\\Core\\Contract\\Webhook\\AbstractWebhookMapper;
use IntegrationEngine\\Infrastructure\\Webhook\\ConsumesWebhookEvents;
use Symfony\\Component\\RemoteEvent\\Attribute\\AsRemoteEventConsumer;
use Symfony\\Component\\RemoteEvent\\Consumer\\ConsumerInterface;

/**
 * Consumes the verified {$ctx->integration}.{$ctx->event} webhook.
 *
 * Symfony hands the RemoteEvent to this consumer through Messenger, keyed by
 * the routing name below. The mapping happens here; put the domain work in a
 * listener of the typed event.
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
final class {$className} implements ConsumerInterface
{
    use ConsumesWebhookEvents;

    protected function mapper(): AbstractWebhookMapper
    {
        return new {$mapperClassName}();
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

        $imports = [
            'use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;',
            'use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;',
            $this->generateVerifierSetup($ctx),
            'use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;',
        ];
        if ('timestamped_hmac' === $ctx->verifierType) {
            $imports[] = 'use Psr\Clock\ClockInterface;';
        }
        $useStatements = implode("\n", array_filter($imports));
        $constructor = $this->generateParserConstructor($ctx);
        $verifierMethod = $this->generateVerifierMethod($ctx);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$useStatements}

/**
 * Webhook request parser for {$integration}.{$event}.
 *
 * Verifies the signature of incoming {$integration} requests and hands the
 * payload over as a RemoteEvent. The signing secret comes from
 * framework.webhook.routing.<key>.secret, so this class needs no wiring of its
 * own: it is autowired as it stands.
 */
final class {$className} extends IntegrationWebhookRequestParser
{
{$constructor}    public function getDefinition(): string
    {
        return '{$event}';
    }

    public function getMapper(): AbstractWebhookMapper
    {
        return new {$mapperClassName}();
    }

{$verifierMethod}    /**
     * Only a fallback: Symfony passes the routing secret to parse(). Both
     * being empty is rejected with a 406, never verified with an empty key.
     */
    protected function getSignatureSecret(): string
    {
        return '';
    }
}
PHP;
    }

    /**
     * Only the timestamped verifier needs anything injected, and a PSR-20
     * clock is autowired like any other service.
     */
    private function generateParserConstructor(WebhookContext $ctx): string
    {
        if ('timestamped_hmac' !== $ctx->verifierType) {
            return '';
        }

        return <<<'PHP'
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}


PHP;
    }

    private function generateVerifierMethod(WebhookContext $ctx): string
    {
        $verifier = match ($ctx->verifierType) {
            'timestamped_hmac' => \sprintf(
                "new TimestampedHmacSignatureVerifier('%s', 300, \$this->clock)",
                $ctx->headerName,
            ),
            'hmac_base64' => \sprintf("new Base64HmacSignatureVerifier('%s')", $ctx->headerName),
            default => \sprintf("new HmacSha256SignatureVerifier('%s', 'sha256=')", $ctx->headerName),
        };

        return <<<PHP
    protected function getSignatureVerifier(): SignatureVerifierInterface
    {
        return {$verifier};
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
            'hmac_base64' => 'use IntegrationEngine\Core\Webhook\Base64HmacSignatureVerifier;',
            'timestamped_hmac' => 'use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;',
            default => '',
        };
    }
}
