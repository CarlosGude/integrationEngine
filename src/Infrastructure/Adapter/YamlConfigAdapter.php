<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Adapter;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\AuthorizationConfig;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Port\ConfigPort;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigAdapter implements ConfigPort
{
    private array $config;

    public function __construct(string $configPath)
    {
        if (!file_exists($configPath)) {
            throw new \InvalidArgumentException(\sprintf('Integration config file not found: %s', $configPath));
        }

        $this->config = Yaml::parseFile($configPath);
    }

    public function getAction(string $name, array $bodyData = []): AbstractAction
    {
        if (!isset($this->config[$name])) {
            throw new ActionNotFoundException($name);
        }

        $actionConfig = $this->config[$name];

        foreach (['action', 'method', 'path'] as $key) {
            if (!isset($actionConfig[$key])) {
                throw new \InvalidArgumentException(\sprintf('Action "%s" is missing required key: "%s".', $name, $key));
            }
        }

        $authorization = isset($actionConfig['authorization'])
            ? AuthorizationConfig::fromArray($actionConfig['authorization'])
            : null;

        $body = null;

        if (isset($actionConfig['body'])) {
            $bodyClass = $actionConfig['body'];

            if (!is_a($bodyClass, ActionBodyInterface::class, true)) {
                throw new \InvalidArgumentException(\sprintf('Body "%s" must implement %s', $bodyClass, ActionBodyInterface::class));
            }

            $body = $bodyClass::create($bodyData);
        }

        return $actionConfig['action']::create(
            method: $actionConfig['method'],
            path: $actionConfig['path'],
            body: $body,
            authorization: $authorization,
        );
    }
}
