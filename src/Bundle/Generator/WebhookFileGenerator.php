<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

use Symfony\Component\Yaml\Yaml;

/** Generates application DTOs and mappers; the bundle provides the parser. */
final class WebhookFileGenerator
{
    /** @return array<string, string> */
    public function generateFiles(WebhookContext $ctx): array
    {
        $namespace = $ctx->namespace();
        $event = $ctx->eventClassName();
        $mapper = $ctx->mapperClassName();
        $type = var_export($ctx->event, true);
        $path = $ctx->generationPath();

        return [
            $path.'/'.$event.'.php' => <<<PHPFILE
<?php

declare(strict_types=1);
namespace {$namespace};

use IntegrationEngine\\Core\\Contract\\Webhook\\WebhookEventInterface;

final readonly class {$event} implements WebhookEventInterface
{
    public function __construct(public string \$id) {}
}
PHPFILE,
            $path.'/'.$mapper.'.php' => <<<PHPFILE
<?php

declare(strict_types=1);
namespace {$namespace};

use IntegrationEngine\\Core\\Contract\\Webhook\\AbstractWebhookMapper;

final class {$mapper} extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return {$type};
    }

    protected static function transform(array \$payload, array \$headers): {$event}
    {
        \$id = \$payload['id'] ?? null;
        if (!\\is_string(\$id) && !\\is_int(\$id)) {
            throw new \\UnexpectedValueException('Webhook event id must be a string or integer.');
        }

        return new {$event}((string) \$id);
    }
}
PHPFILE,
        ];
    }

    /** Merge a new event without overwriting existing actions or signature settings. */
    public function mergeYaml(WebhookContext $ctx, string $existing = '', bool $force = false): string
    {
        $config = '' === trim($existing) ? [] : Yaml::parse($existing);
        if (!\is_array($config)) {
            throw new \InvalidArgumentException('Integration YAML must be a mapping.');
        }
        $webhooks = $config['webhooks'] ?? [
            'type_field' => 'type',
            'id_field' => 'id',
            'signature' => array_filter([
                'type' => $ctx->verifierType,
                'header' => $ctx->headerName,
                'secret' => '%env(WEBHOOK_SECRET)%',
                'tolerance' => 'timestamped_hmac' === $ctx->verifierType ? 300 : null,
            ], static fn (mixed $value): bool => null !== $value),
            'unknown_events' => 'ignore',
            'events' => [],
        ];
        if (!\is_array($webhooks) || !\is_array($webhooks['events'] ?? null)) {
            throw new \InvalidArgumentException('Webhook events must be a mapping.');
        }
        if (!isset($webhooks['events'][$ctx->event]) || $force) {
            $webhooks['events'][$ctx->event] = ['mapper' => $ctx->namespace().'\\'.$ctx->mapperClassName()];
        }
        $config['webhooks'] = $webhooks;

        return Yaml::dump($config, 6, 4);
    }
}
