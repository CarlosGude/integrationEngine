# IntegrationEngine

[![CI](https://github.com/CarlosGude/integrationEngine/actions/workflows/ci.yml/badge.svg)](https://github.com/CarlosGude/integrationEngine/actions/workflows/ci.yml)

**Latest tagged release:** [v8.0.4](https://github.com/CarlosGude/integrationEngine/releases/tag/v8.0.4)
**Requirements:** PHP 8.2+ · Symfony 6.4 / 7.x / 8.x
**Website:** [integrationengine.dev](https://integrationengine.dev)
**Documentation:** [docs/DOCUMENTATION.md](./docs/DOCUMENTATION.md)
**Roadmap:** [docs/ROADMAP.md](./docs/ROADMAP.md)

IntegrationEngine is a Symfony bundle for keeping outbound API integrations predictable: actions describe requests, mappers turn external responses into typed DTOs, and the engine owns the repetitive transport/authentication pipeline.

This README documents the current code in the repository. `CHANGELOG.md` is the source of truth for release history and unreleased changes.

## What the bundle standardizes

An integration is built from a small set of contracts:

- an `AbstractAction` per operation;
- optional `ActionBodyInterface` / `ActionContextInterface` values for runtime input;
- one `AbstractMapper` for each action that returns data;
- a typed `ResponseInterface` DTO;
- an integration facade in the consuming application, backed by `IntegrationRegistry`.

The bundle then handles configuration lookup, path resolution, static or dynamic auth, connection overrides, middleware, HTTP transport, batching, response mapping and lifecycle events.

It intentionally does **not** own your domain model. Keep the external DTOs behind a Gateway / Anti-Corruption Layer when they cross into application or domain code.

## Install

```bash
composer require carlosgude/integration-engine
```

With Symfony Flex, the bundle is registered automatically.

## First integration

Generate the integration shell and first action:

```bash
php bin/console make:integration MyApi GetEmployee
```

The command creates the integration facade, action, mapper/response when applicable, and the integration YAML. On the first integration it can also create `config/packages/integration_engine.yaml`.

Bundle configuration is per integration:

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        my_api:
            base_url: 'https://api.example.com'
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
```

Action configuration lives in the integration YAML:

```yaml
GetEmployee:
    action: App\Infrastructure\Integrations\MyApi\GetEmployee\Request\GetEmployeeAction
    method: GET
    path: /employees/{id}
```

A small facade keeps `IntegrationRegistry` out of controllers and application services:

```php
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;
use IntegrationEngine\Core\Registry\IntegrationName;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final class MyApiIntegration implements IntegrationName
{
    public const NAME = 'my_api';

    public function __construct(private IntegrationRegistry $registry) {}

    public function getEmployee(int $id): GetEmployeeResponse
    {
        $response = $this->registry->get(self::NAME)->send(
            actionName: GetEmployeeAction::getName(),
            context: DefaultActionContext::create(['id' => $id]),
        );

        if (!$response instanceof GetEmployeeResponse) {
            throw new \LogicException('Unexpected response type.');
        }

        return $response;
    }
}
```

See [Getting started](./docs/getting-started/README.md) for the request, context, auth, mapping and batch contracts.

## Current capabilities

| Area | Current contract | Reference |
|---|---|---|
| REST | Built-in Symfony HttpClient adapter; JSON by default | [HTTP clients](./docs/advanced/architecture/clients.md) |
| Form bodies | `FormEncodedBodyInterface`, or `client: form_encoded` for an all-form API | [Form bodies](./docs/form-v8.md) |
| GraphQL | `GraphQLBodyInterface`; body is sent as `query` + `variables` | [HTTP clients](./docs/advanced/architecture/clients.md) |
| Batch | `sendMany()` isolates failures; built-in REST, GraphQL and form adapters support concurrent dispatch when request middleware does not force the sequential fallback | [Batch requests](./docs/getting-started/batch-requests.md) |
| Auth | bearer/basic/API key; dynamic token action with cache + one fresh-token retry after a cached-token 401 | [Authorization](./docs/getting-started/authorization.md) |
| Multi-connection | `ConnectionResolverInterface` can override base URL/auth and namespace token caches per call | [HTTP clients](./docs/advanced/architecture/clients.md#runtime-connection-resolution) |
| Middleware | action-level client middleware plus full-request middleware for signing | [Architecture](./docs/ARCHITECTURE.md#middleware-boundaries) |
| Resilience | transport timeout, max duration and Symfony retry configuration | [Resilience](./docs/resilience-v8.md) |
| Outgoing security | hostname allowlist and optional private-network blocking for built-in transports | [Security](./docs/security-v8.md) |
| Observability | scalar-only request, response, failure, token and webhook events | [Lifecycle events](./docs/LIFECYCLE.md) |
| Webhooks | YAML definition, HMAC verification, typed mapper, Symfony Webhook/RemoteEvent integration | [Webhooks](./docs/WEBHOOK.md) |
| Static analysis | optional PHPStan rules for mapper reciprocity, response modifiers and facade return types | [PHPStan](./docs/phpstan.md) |
| Debugging | `debug:integration [name]` and Symfony profiler integration in debug mode | [Debugging](./docs/advanced/debugging.md) |

## Configuration split

There are two YAML scopes and they solve different problems:

```text
config/packages/integration_engine.yaml
└── integration wiring: base_url, client, transport, headers, cache,
    middlewares, request_middlewares, connection_resolver

src/.../MyApi.yaml
├── action definitions: class, method, path, body, authorization,
│   cache_ttl, timeout
└── optional webhooks definition
```

Keeping those scopes separate matters: bundle configuration decides **how an integration is wired**; integration YAML describes **what operations that integration exposes**.

## Architecture in one sentence

`Core` defines contracts and orchestration; `Infrastructure` implements adapters; `Bundle` wires Symfony; compatibility shims stay outside Core; optional PHPStan rules live in their own extension layer. The dependency direction is enforced by Deptrac.

The detailed dependency rules and runtime pipeline are in [ARCHITECTURE.md](./docs/ARCHITECTURE.md).

## Quality

Repository gates cover style, PHPStan, PHPUnit, Deptrac and Infection. Measured results belong in [docs/advanced/QUALITY.md](./docs/advanced/QUALITY.md), not in this README, so release copy does not become a museum of stale numbers.

## Demo

[integrationEngine-demo](https://github.com/CarlosGude/integrationEngine-demo) is the consuming Symfony application used to demonstrate and contract-test the bundle.

## Further reading

Start from [docs/DOCUMENTATION.md](./docs/DOCUMENTATION.md). It is the only documentation index; archived plans, spikes and release-preparation notes live under [docs/archived/](./docs/archived/) and are explicitly non-current.

## When not to use it

IntegrationEngine is a poor fit when an API needs a highly stateful SDK, protocol-specific streaming/session behavior, or a vendor library that already owns transport, retries and object mapping coherently. In those cases, wrapping the SDK behind your own application boundary is usually simpler than forcing it into this action/mapper model.
