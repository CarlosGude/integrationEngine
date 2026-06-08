# IntegrationEngine Documentation

## Mental model

An integration is a directory.

An endpoint is a folder.

Each endpoint contains:

- Request (input side)
- Response (output side)

Nothing else is allowed to spread.

---

## Lifecycle of an integration

1. Define integration
2. Generate endpoints
3. Implement request + response
4. Register integration
5. Use via engine

---

## Sending a request

```php
$response = $integrationEngine->send(
    actionName: GetEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 123])
);
```

The engine resolves everything:

- Action
- Request
- Transport
- Response mapping

---

## Integration structure

Each endpoint is split into two parts:

### Request side
- Action
- Context
- Transport input mapping

### Response side
- DTO
- Mapper
- Output normalization

---

## Registry

Integrations are resolved via registry:

```php
$engine = $registry->get('dummy_rest_api');
```

---

## Cache behavior

⚠️ Important:

The in-memory cache adapter is process-scoped.

It is not suitable for:

- Multi-worker auth systems
- Distributed environments

Use it only for request lifecycle caching.

---

## Design principles

- Predictability over flexibility
- Structure over freedom
- Convention over invention
- Uniformity across all integrations

---

## Anti-patterns avoided

- No scattered HTTP clients
- No per-integration architecture drift
- No duplicated mapping logic
- No inconsistent endpoint structure
