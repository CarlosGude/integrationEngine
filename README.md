# IntegrationEngine

**Version:** 8.0.1
**PHP:** >=8.2 | **Symfony:** 6.4, 7.x, 8.x
**Packagist:** [carlosgude/integration-engine](https://packagist.org/packages/carlosgude/integration-engine)
**Demo:** [integrationengine.dev](https://integrationengine.dev)

---

## What it is

IntegrationEngine is a Symfony bundle for building predictable external API integrations. It standardizes request/response mapping, authentication, connection resolution, batch execution, middleware, and webhooks — while keeping application-specific concerns (resilience, queues, domain logic) outside the engine.

It exists because integrating with 20 different external APIs should not mean writing 20 different HTTP clients from scratch.

---

## Core concepts

- **Actions & Mappers** — Define an external call and map its response to a typed DTO.
- **Client Adapters** — REST, GraphQL, form-encoded, and custom adapters behind the same contract.
- **Middleware Pipeline** — Tagged middleware with priority ordering, applied per integration.
- **Request Middleware** — Sign fully-built requests (OAuth 1.0a, AWS SigV4, etc.) before transport.
- **Connection Resolver** — Multi-tenant / multi-connection support with per-connection auth caching.
- **Webhooks** — Inbound webhook parsing, signature verification, typed events, and idempotency.
- **Lifecycle Events** — Observability hooks for action start, HTTP response, mapping, completion, and failure.

---

## Golden path

```text
Application
    ↓
Gateway / Application Service
    ↓
IntegrationEngine
    ↓
External Provider (REST, GraphQL, CSV, webhook)
    ↓
Typed Response / Event
    ↓
Application
```

The domain never sees the external provider, the engine, or Symfony HTTP Client. That boundary is the point.

---

## Installation

```bash
composer require carlosgude/integration-engine
```

For inbound webhooks, you also need:

```bash
composer require symfony/webhook symfony/remote-event symfony/messenger
```

---

## Quick example

```php
// 1. Define an Action
class GetMovieAction extends AbstractAction
{
    public function getMethod(): string { return 'GET'; }
    public function getPath(): string { return '/movie/{id}'; }
}

// 2. Define a Mapper
class GetMovieMapper extends AbstractMapper
{
    public function map(ResponseInterface $response): Movie
    {
        $data = $response->toArray();
        return new Movie($data['title'], $data['year']);
    }
}

// 3. Configure the integration
integration_engine:
    integrations:
        tmdb:
            base_url: '%env(TMDB_BASE_URL)%'
            auth:
                type: api_key
                token: '%env(TMDB_API_KEY)%'

// 4. Use it
$movie = $engine->send(new GetMovieAction(['id' => 550]));
```

---

## What it does NOT do

- **Resilience** (retry, circuit breaker, fallback) — This is application-level policy. The engine provides middleware hooks; your app composes the policy.
- **Messaging / Queueing** — The engine emits events. Your app decides sync vs async, Messenger, RabbitMQ, etc.
- **Domain logic** — The engine maps data. Your domain decides what it means.

This boundary is deliberate. It keeps the engine small, predictable, and replaceable.

---

## Quality

- PHPStan level **max**
- Mutation testing (Infection, 92%+ MSI)
- 390+ tests
- CI gated on GitHub Actions

See [QUALITY.md](./docs/QUALITY.md) for current thresholds and evidence.

---

## Documentation

- [Getting Started](./docs/GETTING-STARTED.md)
- [Architecture](./docs/ARCHITECTURE.md)
- [Webhooks](./docs/WEBHOOK.md)
- [Middleware](./docs/MIDDLEWARE.md)
- [Connections & Multi-tenancy](./docs/CONNECTIONS.md)
- [Lifecycle & Observability](./docs/LIFECYCLE.md)
- [Public API](./docs/PUBLIC-API.md)
- [Roadmap](./docs/ROADMAP.md)

---

## Demo

A working demo with REST, GraphQL, CSV, and Stripe webhook flows:
[integrationengine.dev](https://integrationengine.dev)

---

## License

MIT