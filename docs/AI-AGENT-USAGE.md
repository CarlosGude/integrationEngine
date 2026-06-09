# Generating integrations with an AI agent

> ⚠️ **Experimental.** This workflow is under active development. Generated code requires
> manual review before use in production. The agent may make mistakes — treat its output
> as a first draft, not a final result.

---

## What this enables

You can point a compatible AI agent (Claude, GPT-4, etc.) at an API documentation URL
and have it generate a complete IntegrationEngine integration: Actions, Mappers, DTOs,
Response classes, YAML config, and a typed facade.

The agent uses the context in [`.agent/integration-engine-agent-guide.md`](./.agent/integration-engine-agent-guide.md)
to understand the engine's contracts and follow its conventions.

---

## Requirements

- An AI assistant with web browsing capability (Claude with web search, ChatGPT with
  browsing, etc.)
- The `.agent/integration-engine-agent-guide.md` file loaded into the agent's context
- The API documentation URL (REST or GraphQL)

---

## Step-by-step

### 1. Load the agent guide

Paste the contents of `.agent/integration-engine-agent-guide.md` into the conversation,
or attach the file directly. Tell the agent:

```
You are generating a Symfony IntegrationEngine integration.
Follow the conventions and rules in this guide exactly.
```

### 2. Provide the API documentation

Give the agent a URL or paste the relevant sections of the API documentation:

```
Generate a complete IntegrationEngine integration for this API:
https://api.example.com/documentation
```

The agent will read the documentation and identify the available endpoints, schemas,
and authentication requirements.

### 3. Review the query param decision

For any endpoint with optional filter parameters, the agent will pause and ask you
to confirm which approach to use before generating code:

```
The API declares these filter params for /employees: name, department, status.
→ Are all of them required, or can any be omitted?

  [A] All required  → YAML placeholders: path: /employees?name={name}&...
                       No resolvePathCallback needed.
  [B] Some optional → resolvePathCallback with http_build_query.

Which fits this API?
```

Answer `A` or `B`. The agent will not generate the filter action until you confirm.

### 4. Collect the output

The agent will generate:

```
src/Infrastructure/Integrations/{Name}/
├── {Name}Integration.php
├── {Name}.yaml
├── Dto/
│   └── {Entity}.php
└── {ActionName}/
    ├── Request/
    │   └── {ActionName}Action.php
    └── Response/
        ├── {ActionName}Response.php
        └── {ActionName}Mapper.php

config/packages/integration_engine.yaml
```

### 5. Review the generated code

Before using the integration, verify the following manually:

- [ ] Every `Filter*Action` points to its own `Filter*Mapper`, not to `GetAll*Mapper`
- [ ] Every `Filter*Mapper::getAction()` returns the `Filter*Action::class`, not `GetAll*Action::class`
- [ ] DTOs with two actions of the same shape use a `*CollectionTransformer` class
- [ ] Dynamic auth token field name matches the field in the auth response DTO's `toArray()`
- [ ] Base URL in `integration_engine.yaml` matches the API's production endpoint
- [ ] Environment variables are used for secrets (`'%env(MY_API_TOKEN)%'`), not hardcoded values

### 6. Install and clear cache

```bash
composer require carlosgude/integration-engine  # if not already installed
php bin/console cache:clear
```

---

## Known limitations

**The agent cannot test the integration.** It generates code based on the documentation,
but does not make real HTTP calls. Verify that field names, response shapes, and auth
flows match the actual API behaviour.

**Documentation gaps become code gaps.** If the API documentation is incomplete or
ambiguous, the agent will make reasonable assumptions — but they may be wrong. Pay
particular attention to nullable fields, pagination wrappers, and error response shapes.

**Custom path resolution is error-prone.** The agent generates `resolvePathCallback`
correctly for standard filter patterns, but complex path logic (versioned URLs, dynamic
base paths, content negotiation) may require manual adjustment.

**GraphQL introspection is not used.** The agent reads documentation prose, not the
GraphQL schema. If the schema differs from the documentation, the generated queries
may not match.

---

## Example prompt

```
I need a complete IntegrationEngine integration for the Rick and Morty API.
Documentation: https://rickandmortyapi.com/documentation#rest

The integration should cover all three resources: Character, Location, Episode.
Include listing, single-item, and filter actions for each.

Follow the agent guide I've attached and ask me to confirm the query param
strategy before generating any filter actions.
```

---

## Reporting issues

If the agent generates incorrect code — wrong mapper wiring, missing transformer
classes, incorrect auth config — please open an issue and include:

1. The API documentation URL
2. The agent's output
3. The specific error or incorrect pattern

This helps improve the agent guide for everyone.
