# IntegrationEngine

[![CI](https://github.com/CarlosGude/integrationEngine/actions/workflows/ci.yml/badge.svg)](https://github.com/CarlosGude/integrationEngine/actions/workflows/ci.yml)

**Latest tagged release:** [see releases](https://github.com/CarlosGude/integrationEngine/releases/latest)
**Requirements:** PHP 8.2+ · Symfony 6.4 / 7.x / 8.x
**Website:** [integrationengine.dev](https://integrationengine.dev)
**Documentation:** [docs/documentation.md](./docs/documentation.md)
**Roadmap:** [docs/roadmap.md](./docs/roadmap.md)

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

## When not to use it

IntegrationEngine is a poor fit when an API needs a highly stateful SDK, protocol-specific streaming/session behavior, or a vendor library that already owns transport, retries and object mapping coherently. In those cases, wrapping the SDK behind your own application boundary is usually simpler than forcing it into this action/mapper model.

## Why this exists

Integrations tend to accumulate their own ad-hoc HTTP client, their own way of attaching auth, and their own untyped response handling, repeated slightly differently for every external API a project talks to. That inconsistency shows up as duplicated token logic, DTOs that drift from what the API actually returns, and observability that exists for some integrations and not others. This bundle grew out of exactly that pattern in a production integration, not from a speculative design exercise. The response is a small, uniform contract — Action, Mapper, Response — so every integration looks like every other one and can be tested in isolation.

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

At the lowest level, calling an action is a single `send()` through the registry:

```php
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;

$response = $registry->get('my_api')->send(
    actionName: GetEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => $id]),
);
```

In practice, wrap that behind a small facade instead of calling `IntegrationRegistry` directly from controllers and application services, so callers get a typed return instead of the base `ResponseInterface`.

The facade implements `IntegrationName` so the integration is referenced as `MyApiIntegration::NAME` rather than a bare string — the constant is autocompleted and can be grepped/refactored with the rest of the codebase, instead of relying on string literals that silently drift. This is the recommended facade pattern:

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

A failed call (HTTP 4xx/5xx, or a network error) throws `RequestResponseException`, which carries the status code and context:

```php
use IntegrationEngine\Core\Exception\RequestResponseException;

try {
    $response = $this->registry->get(self::NAME)->send(/* ... */);
} catch (RequestResponseException $e) {
    // $e->statusCode, $e->context
}
```

See [Getting started](./docs/getting-started/README.md) for the request, context, auth, mapping and batch contracts.

## Current capabilities

| Area | Current contract | Reference |
|---|---|---|
| REST | Built-in Symfony HttpClient adapter; JSON by default | [HTTP clients](./docs/advanced/architecture/clients.md) |
| Form bodies | `FormEncodedBodyInterface`, or `client: form_encoded` for an all-form API | [Form bodies](./docs/form-bodies.md) |
| GraphQL | `GraphQLBodyInterface`; body is sent as `query` + `variables` | [HTTP clients](./docs/advanced/architecture/clients.md) |
| Batch | `sendMany()` isolates failures; built-in REST, GraphQL and form adapters support concurrent dispatch when request middleware does not force the sequential fallback | [Batch requests](./docs/getting-started/batch-requests.md) |
| Auth | bearer/basic/API key; dynamic token action with cache + one fresh-token retry after a cached-token 401 | [Authorization](./docs/getting-started/authorization.md) |
| Multi-connection | `ConnectionResolverInterface` can override base URL/auth and namespace token caches per call | [HTTP clients](./docs/advanced/architecture/clients.md#runtime-connection-resolution) |
| Middleware | action-level client middleware plus full-request middleware for signing | [Architecture](./docs/architecture.md#middleware-boundaries) |
| Resilience | transport timeout, max duration and Symfony retry configuration | [Resilience](./docs/resilience.md) |
| Outgoing security | hostname allowlist and optional private-network blocking for built-in transports | [Security](./docs/security.md) |
| Observability | scalar-only request, response, failure, token and webhook events | [Lifecycle events](./docs/lifecycle.md) |
| Webhooks | YAML definition, HMAC verification, typed mapper, Symfony Webhook/RemoteEvent integration | [Webhooks](./docs/webhook.md) |
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

The detailed dependency rules and runtime pipeline are in [architecture.md](./docs/architecture.md).

## Quality

Repository gates cover style, PHPStan, PHPUnit, Deptrac and Infection. Measured results belong in [docs/advanced/quality.md](./docs/advanced/quality.md), not in this README, so release copy does not become a museum of stale numbers.

## Demo

[integrationEngine-demo](https://github.com/CarlosGude/integrationEngine-demo) is the consuming Symfony application used to demonstrate and contract-test the bundle.

## Further reading

Start from [docs/documentation.md](./docs/documentation.md). It is the only documentation index; archived plans, spikes and release-preparation notes live under [docs/archived/](./docs/archived/) and are explicitly non-current.

A rough guide to where things live:

- [docs/getting-started/](./docs/getting-started/) — task-oriented guides for building a first integration: actions, context/path, auth, mapping, batching.
- [docs/advanced/](./docs/advanced/) — client/architecture internals, debugging, quality gates, AI-agent-assisted generation.
- [docs/adr/](./docs/adr/) — Architecture Decision Records; read these for *why* a design was chosen, not just *what* it does.
- [docs/archived/](./docs/archived/) — historical plans, audits and spikes; explicitly not the current contract.
- `docs/architecture.md` is the single reference for dependency rules and the runtime pipeline.
- Breaking-change migration notes live under `docs/`, one `upgrade-N.0.md` file per major version.
