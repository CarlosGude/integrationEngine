# Generating an integration

`make:integration` writes the **structure** of an integration: the classes, the YAML entry and, the first time, the bundle configuration. The **logic** — which fields the response carries, how they are validated and which methods the facade exposes — is yours to write. This page covers what the command asks, what it creates and what is left to complete.

## Running the command

```bash
php bin/console make:integration MyApi GetEmployee
```

| Argument / option | Meaning |
|---|---|
| `name` | Integration name in PascalCase. The bundle registers it under its snake_case form (`my_api`). |
| `action` | Action name. Asked interactively when omitted. |
| `--namespace` | Base namespace. Default: `App\Infrastructure\Integrations`. |
| `--path` | Base directory, relative to the project. Default: `src/Infrastructure/Integrations`. |
| `--force` | Overwrite generated PHP files that already exist. Without it they are skipped. |

The command asks:

- **Base URL and client type**, when the integration is not registered in `config/packages/integration_engine.yaml` yet. The client types offered are the registered adapters (`rest`, `graphql`, `form_encoded` and any of your own).
- **Path and HTTP method**, when the adapter needs them (`rest`, `form_encoded`). A `graphql` integration asks neither.

With `--no-interaction` every question takes its default; there is no default base URL, so the bundle configuration is not written (see below).

## What it creates

```text
config/packages/integration_engine.yaml     ← only when the file does not exist yet
src/Infrastructure/Integrations/MyApi/
├─ MyApi.yaml                               ← one entry appended per new action
├─ MyApiIntegration.php                     ← only the first time for this integration
└─ GetEmployee/
   ├─ Request/GetEmployeeAction.php
   └─ Response/                             ← not generated for DELETE
      ├─ GetEmployeeMapper.php
      └─ GetEmployeeResponse.php
```

Running the command again for another action adds that action's files and its YAML entry next to the existing ones. Existing PHP files are never overwritten without `--force`, and an action already declared in the integration YAML is not appended a second time.

| File | What the generator writes | What you complete |
|---|---|---|
| `MyApi.yaml` | `action`, plus `method` and `path` for adapters that use them | Optional keys: `body`, `authorization`, `cache_ttl`, `timeout` ([Actions](actions.md#action-yaml)) |
| `MyApiIntegration.php` | The class implementing `IntegrationName` with its `NAME` constant | The facade: inject `IntegrationRegistry` and add one typed method per action ([README](../../README.md#first-integration)) |
| `GetEmployeeAction.php` | `getName()`, `hasResponse()` (`false` for DELETE) and `mapper()` | Usually nothing |
| `GetEmployeeResponse.php` | An empty `readonly` DTO implementing `ResponseInterface` | The fields the API returns, its factory and `toArray()` ([Mappers and responses](mappers-and-responses.md)) |
| `GetEmployeeMapper.php` | `getAction()` paired with the action, and a `transform()` that throws `LogicException` | The mapping, rejecting missing or invalid required fields ([Mappers and responses](mappers-and-responses.md#required-and-optional-fields)) |

The generated `transform()` throws on purpose: until you implement it, a call fails with a message naming the mapper instead of returning an empty DTO that looks like a valid response.

Request bodies and custom contexts are not generated. Add an `ActionBodyInterface` class and declare it under `body:` when the action sends a payload, and a custom context when path parameters need validation ([Context and path](context-and-path.md)).

## Registering the integration

The first time the command runs in a project without `config/packages/integration_engine.yaml`, it creates that file with the base URL and client type you entered.

When the file already exists and does not declare the integration — your second integration, for example — the command does **not** edit it, so your comments and formatting stay intact. It prints the block to add instead:

```text
 [WARNING] The "OtherApi" integration is not registered in .../config/packages/integration_engine.yaml,
           so the bundle will not load it. Add this to that file:

integration_engine:
    integrations:
        other_api:
            base_url: 'https://other.example.com'
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/OtherApi/OtherApi.yaml'
```

The same warning appears after a `--no-interaction` run, with a placeholder `base_url` to replace. Every key the bundle accepts is in [Bundle configuration](../configuration.md).

## Checklist after generating

1. Add the response fields and validate them in the mapper.
2. Add the facade method that calls `send()` and returns the typed response.
3. Add the YAML keys the endpoint needs (authorization, body, timeout).
4. If the command printed the registration warning, add the block to `config/packages/integration_engine.yaml`.
5. Check the wiring with `php bin/console debug:integration my_api` ([Debugging](../advanced/debugging.md)).
