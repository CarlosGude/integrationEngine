<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Adapter;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Auth\AuthorizationConfig;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\UnknownEventPolicy;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Port\ConfigPort;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigAdapter implements ConfigPort
{
    /** @var array<string, array{action: class-string<AbstractAction>, method?: string, path?: string, body?: class-string, authorization?: null|array<string, mixed>|false, cache_ttl?: int, timeout?: float}> */
    private array $config;

    /** @var array<string, mixed> */
    private array $webhooks;

    /** @var null|array<string, mixed> */
    private ?array $defaultAuthorization;

    public function __construct(string $configPath, private readonly ?string $webhookSecret = null)
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

        $webhooks = $parsed['webhooks'] ?? [];
        $defaults = $parsed['defaults'] ?? [];
        $actions = [];

        foreach ($parsed as $key => $item) {
            if ('webhooks' === $key || 'defaults' === $key) {
                continue;
            }
            $actions[$key] = $item;
        }

        foreach ($actions as $actionName => $actionConfig) {
            if (!\is_array($actionConfig) || !isset($actionConfig['action']) || !\is_string($actionConfig['action'])) {
                throw new \InvalidArgumentException(
                    \sprintf('Action "%s" must define a string "action" class in the integration YAML.', $actionName)
                );
            }
            if (isset($actionConfig['timeout'])) {
                $timeout = $actionConfig['timeout'];
                if (!\is_int($timeout) && !\is_float($timeout)) {
                    throw new \InvalidArgumentException('timeout must be a finite non-negative number');
                }
                if ($timeout < 0 || is_infinite($timeout) || is_nan($timeout)) {
                    throw new \InvalidArgumentException('timeout must be a finite non-negative number');
                }
            }
            if (\array_key_exists('authorization', $actionConfig)) {
                $ownAuthorization = $actionConfig['authorization'];
                if (null !== $ownAuthorization && false !== $ownAuthorization && !\is_array($ownAuthorization)) {
                    throw new \InvalidArgumentException(\sprintf('Action "%s" authorization must be a map, false, or null (~), got %s.', $actionName, get_debug_type($ownAuthorization)));
                }
            }
        }

        /** @var array<string, array{action: class-string<AbstractAction>, method?: string, path?: string, body?: class-string, authorization?: null|array<string, mixed>|false, cache_ttl?: int, timeout?: float}> $actions */
        $this->config = $actions;

        if (!\is_array($webhooks)) {
            throw new \InvalidArgumentException('Webhooks section in config must be an array.');
        }

        /** @var array<string, mixed> $webhooks */
        $this->webhooks = $webhooks;

        if (!\is_array($defaults)) {
            throw new \InvalidArgumentException('Defaults section in config must be a map.');
        }

        $defaultAuthorization = $defaults['authorization'] ?? null;
        if (null !== $defaultAuthorization && !\is_array($defaultAuthorization)) {
            throw new \InvalidArgumentException('defaults.authorization must be a map.');
        }

        /** @var null|array<string, mixed> $defaultAuthorization */
        if (null !== $defaultAuthorization) {
            AuthorizationConfig::fromArray($defaultAuthorization);
        }

        $this->defaultAuthorization = $defaultAuthorization;
    }

    public function getAction(string $name, ?ActionBodyInterface $bodyData = null): AbstractAction
    {
        if (!isset($this->config[$name])) {
            throw new ActionNotFoundException($name);
        }

        $actionConfig = $this->config[$name];

        $authorization = $this->resolveAuthorization($actionConfig);

        $body = $this->resolveBody($name, $actionConfig, $bodyData);

        $actionClass = $actionConfig['action'];

        $this->validateActionClass($actionClass);

        return $actionClass::create(
            method: $actionConfig['method'] ?? 'POST',
            path: $actionConfig['path'] ?? '/',
            body: $body,
            authorization: $authorization,
            cacheTtl: isset($actionConfig['cache_ttl']) ? (int) $actionConfig['cache_ttl'] : null,
            timeout: $actionConfig['timeout'] ?? null,
        );
    }

    public function getWebhookDefinition(): ?WebhookDefinition
    {
        if ([] === $this->webhooks) {
            return null;
        }
        $signature = $this->webhooks['signature'] ?? null;
        $events = $this->webhooks['events'] ?? null;
        $typeField = $this->webhooks['type_field'] ?? null;
        $idField = $this->webhooks['id_field'] ?? null;
        $policy = $this->webhooks['unknown_events'] ?? 'ignore';
        if (!\is_array($signature) || !\is_array($events) || !\is_string($typeField) || !\is_string($idField) || !\is_string($policy)) {
            throw new \InvalidArgumentException('Webhooks require signature, events, type_field and id_field.');
        }
        if (null !== $this->webhookSecret) {
            $signature['secret'] = $this->webhookSecret;
        }
        $mappers = [];
        foreach ($events as $eventType => $event) {
            if (!\is_string($eventType) || !\is_array($event) || !isset($event['mapper']) || !\is_string($event['mapper']) || !is_subclass_of($event['mapper'], AbstractWebhookMapper::class)) {
                throw new \InvalidArgumentException('Each webhook event must declare a mapper extending AbstractWebhookMapper.');
            }
            $mappers[$eventType] = $event['mapper'];
        }
        $unknownEvents = UnknownEventPolicy::tryFrom($policy);
        if (null === $unknownEvents) {
            throw new \InvalidArgumentException('unknown_events must be ignore or reject.');
        }

        /** @var array<string, mixed> $signature */
        return new WebhookDefinition($typeField, $idField, SignatureConfig::fromArray($signature), $unknownEvents, $mappers);
    }

    /**
     * Merges the action's own `authorization:` on top of `defaults.authorization`
     * (array-shallow, action keys win) when both share a type, or the action
     * doesn't declare one. An explicit `authorization: false` (or `~`) opts
     * out of the default entirely, distinct from the key being absent.
     *
     * @param array{authorization?: null|array<string, mixed>|false} $actionConfig
     */
    private function resolveAuthorization(array $actionConfig): ?AuthorizationConfig
    {
        $own = $actionConfig['authorization'] ?? null;

        if (false === $own) {
            return null;
        }

        if (null === $own) {
            if (\array_key_exists('authorization', $actionConfig)) {
                return null; // explicit `authorization: ~`
            }

            return null !== $this->defaultAuthorization ? AuthorizationConfig::fromArray($this->defaultAuthorization) : null;
        }

        if (null !== $this->defaultAuthorization && (!isset($own['type']) || $own['type'] === $this->defaultAuthorization['type'])) {
            $own = [...$this->defaultAuthorization, ...$own];
        }

        return AuthorizationConfig::fromArray($own);
    }

    /**
     * @param array{action: class-string<AbstractAction>, method?: string, path?: string, body?: class-string, authorization?: null|array<string, mixed>|false} $actionConfig
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

        if (null !== $bodyData && !$bodyData instanceof $bodyClass) {
            throw new \InvalidArgumentException(\sprintf(
                'Action "%s" declares body "%s" in its YAML config, but received "%s".',
                $name,
                $bodyClass,
                $bodyData::class,
            ));
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
