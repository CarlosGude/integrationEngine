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
    /** @var array<string, array{action: class-string<AbstractAction>, method: string, path: string, body?: class-string, authorization?: array<string, mixed>}> */
    private array $config;

    public function __construct(string $configPath)
    {
        if (!file_exists($configPath)) {
            throw new \InvalidArgumentException(\sprintf('Integration config file not found: %s', $configPath));
        }

        /** @var array<string, array{action: class-string<AbstractAction>, method: string, path: string, body?: class-string, authorization?: array<string, mixed>}> $parsed */
        $parsed = Yaml::parseFile($configPath);
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

        $body = null;

        if (isset($actionConfig['body'])) {
            $bodyClass = $actionConfig['body'];

            if (!is_a($bodyClass, ActionBodyInterface::class, true)) {
                throw new \InvalidArgumentException(\sprintf('Body "%s" must implement %s', $bodyClass, ActionBodyInterface::class));
            }

            $body = $bodyData ?? $bodyClass::create([]);
        }

        return $actionConfig['action']::create(
            method: $actionConfig['method'],
            path: $actionConfig['path'],
            body: $body,
            authorization: $authorization,
        );
    }
}
