<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Adapter;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Auth\AuthorizationConfig;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Port\ConfigPort;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigAdapter implements ConfigPort
{
    /** @var array<string, array{action: class-string<AbstractAction>, method?: string, path?: string, body?: class-string, authorization?: array<string, mixed>}> */
    private array $config;

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

        foreach ($parsed as $actionName => $actionConfig) {
            if (!\is_array($actionConfig) || !isset($actionConfig['action']) || !\is_string($actionConfig['action'])) {
                throw new \InvalidArgumentException(
                    \sprintf('Action "%s" must define a string "action" class in the integration YAML.', $actionName)
                );
            }
        }

        /** @var array<string, array{action: class-string<AbstractAction>, method?: string, path?: string, body?: class-string, authorization?: array<string, mixed>}> $parsed */
        $this->config = $parsed;
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

        $actionClass = $actionConfig['action'];

        $this->validateActionClass($actionClass);

        return $actionClass::create(
            method: $actionConfig['method'] ?? 'POST',
            path: $actionConfig['path'] ?? '/',
            body: $body,
            authorization: $authorization,
        );
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
