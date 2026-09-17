<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Adapter;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Auth\AuthorizationConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Exception\PathResolutionException;
use IntegrationEngine\Core\Port\ConfigPort;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigAdapter implements ConfigPort
{
    /** @var array<string, array{action: class-string<AbstractAction>, method?: string, path?: string, body?: class-string, authorization?: array<string, mixed>, cache_ttl?: int}> */
    private array $config;

    /** @var array<string, array<string, mixed>> */
    private array $webhooks;

    public function __construct(string $configPath)
    {
        if (!file_exists($configPath)) {
            throw new \InvalidArgumentException(\sprintf('Integration config file not found: %s', $configPath));
        }

        $parsed = Yaml::parseFile($configPath);

        if (!\is_array($parsed)) {
            throw new \InvalidArgumentException(
                \sprintf('Integration config file "%s" is empty or invalid.', $configPath)
            );
        }

        // Separate webhooks from actions
        $webhooks = $parsed['webhooks'] ?? [];
        $actions = [];

        foreach ($parsed as $key => $item) {
            if ('webhooks' === $key) {
                continue;
            }
            $actions[$key] = $item;
        }

        // Validate actions
        foreach ($actions as $actionName => $actionConfig) {
            if (!\is_array($actionConfig) || !isset($actionConfig['action']) || !\is_string($actionConfig['action'])) {
                throw new \InvalidArgumentException(
                    \sprintf('Action "%s" must define a string "action" class in the integration YAML.', $actionName)
                );
            }
        }

        /** @var array<string, array{action: class-string<AbstractAction>, method?: string, path?: string, body?: class-string, authorization?: array<string, mixed>, cache_ttl?: int}> $actions */
        $this->config = $actions;

        // Validate webhooks
        if (!\is_array($webhooks)) {
            throw new \InvalidArgumentException('Webhooks section in config must be an array.');
        }

        /** @var array<string, array{mapper: string, signature: array<string, mixed>}> $webhooks */
        $this->webhooks = $webhooks;
    }

    public function getAction(string $name, ?ActionBodyInterface $bodyData = null): AbstractAction
    {
        if (!isset($this->config[$name])) {
            throw new ActionNotFoundException($name);
        }

        $actionConfig = $this->config[$name];

        $authorization = isset($actionConfig['authorization'])
            ? AuthorizationConfig::fromArray($actionConfig['authorization'])
            : null;

        $body = $this->resolveBody($name, $actionConfig, $bodyData);
        [$path, $body] = $this->resolvePathPlaceholders($actionConfig['path'] ?? '/', $body);

        $actionClass = $actionConfig['action'];

        $this->validateActionClass($actionClass);

        return $actionClass::create(
            method: $actionConfig['method'] ?? 'POST',
            path: $path,
            body: $body,
            authorization: $authorization,
            cacheTtl: isset($actionConfig['cache_ttl']) ? (int) $actionConfig['cache_ttl'] : null,
        );
    }

    public function getWebhookDefinition(string $eventType): WebhookDefinition
    {
        if (!isset($this->webhooks[$eventType])) {
            throw new \InvalidArgumentException(\sprintf('Webhook event type "%s" not defined in config.', $eventType));
        }

        $webhookConfig = $this->webhooks[$eventType];

        if (!isset($webhookConfig['mapper']) || !\is_string($webhookConfig['mapper'])) {
            throw new \InvalidArgumentException(\sprintf(
                'Webhook event type "%s" must define a string "mapper" class.',
                $eventType,
            ));
        }

        if (!isset($webhookConfig['signature']) || !\is_array($webhookConfig['signature'])) {
            throw new \InvalidArgumentException(\sprintf(
                'Webhook event type "%s" must define a "signature" config (array).',
                $eventType,
            ));
        }

        /** @var array<string, mixed> $signatureConfig */
        $signatureConfig = $webhookConfig['signature'];
        $signature = SignatureConfig::fromArray($signatureConfig);

        /** @var class-string $mapperClass */
        $mapperClass = $webhookConfig['mapper'];

        return new WebhookDefinition(
            eventType: $eventType,
            mapperClass: $mapperClass,
            signature: $signature,
        );
    }

    /**
     * Resolves {name} placeholders in the path using values available on
     * the action's body, and strips consumed keys from the body so they
     * aren't also sent as a body/query parameter.
     *
     * Placeholders absent from the body are left untouched in the path —
     * AbstractAction::getPath() resolves those from ActionContextInterface
     * at send time, and rejects the request there if neither source
     * supplies them. This keeps body values taking priority without this
     * adapter needing to know about context at all.
     *
     * @return array{0: string, 1: ?ActionBodyInterface}
     */
    private function resolvePathPlaceholders(string $path, ?ActionBodyInterface $body): array
    {
        if (null === $body) {
            return [$path, $body];
        }

        $data = $body->toArray();

        // Tracked separately from $data (read-only throughout the callback,
        // never mutated) so a placeholder name repeated more than once in
        // the path resolves every occurrence instead of only the first —
        // deleting the key from $data after the first match would make
        // subsequent matches of the same name see it as absent.
        $consumed = [];

        $resolvedPath = preg_replace_callback(
            '/\{(\w+)}/',
            static function (array $matches) use ($data, &$consumed): string {
                $key = $matches[1];

                if (!\array_key_exists($key, $data)) {
                    return $matches[0];
                }

                $value = $data[$key];
                if (!\is_scalar($value)) {
                    throw PathResolutionException::nonScalarParameter($key);
                }

                $consumed[$key] = true;

                return (string) $value;
            },
            $path,
        ) ?? throw PathResolutionException::pcreError($path);

        if ([] === $consumed) {
            return [$path, $body];
        }

        return [$resolvedPath, $body::create(array_diff_key($data, $consumed))];
    }

    /**
     * @param array{action: class-string<AbstractAction>, method?: string, path?: string, body?: class-string, authorization?: array<string, mixed>} $actionConfig
     */
    private function resolveBody(
        string $name,
        array $actionConfig,
        ?ActionBodyInterface $bodyData,
    ): ?ActionBodyInterface {
        if (!isset($actionConfig['body'])) {
            if (null !== $bodyData) {
                throw new \InvalidArgumentException(\sprintf(
                    'Action "%s" does not declare a body in its YAML config, but a body was provided (%s).',
                    $name,
                    $bodyData::class,
                ));
            }

            return null;
        }

        $bodyClass = $actionConfig['body'];

        if (!is_a($bodyClass, ActionBodyInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf('Body "%s" must implement %s', $bodyClass, ActionBodyInterface::class));
        }

        return $bodyData ?? $bodyClass::create([]);
    }

    private function validateActionClass(string $actionClass): void
    {
        if (!class_exists($actionClass) || !is_a($actionClass, AbstractAction::class, true)) {
            throw new \InvalidArgumentException(
                \sprintf('Action class "%s" does not exist or does not extend %s. Check the "action" entry in your integration YAML.', $actionClass, AbstractAction::class)
            );
        }
    }
}
