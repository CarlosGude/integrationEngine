# Getting Started with IntegrationEngine

Quick-start guides for developers new to IntegrationEngine. Start here if you're building your first integration, and read them in this order:

1. **[generating-an-integration.md](./generating-an-integration.md)** — what `make:integration` creates and what you complete.
2. **[actions.md](./actions.md)** — one class and one YAML entry per external operation.
3. **[context-and-path.md](./context-and-path.md)** — path parameters, query strings and filters.
4. **[authorization.md](./authorization.md)** — static credentials, dynamic tokens and token caching.
5. **[mappers-and-responses.md](./mappers-and-responses.md)** — turning the raw response into a typed DTO, and what the engine validates versus what your mapper must.
6. **[batch-requests.md](./batch-requests.md)** — several requests at once, with per-item failures.

Next: [Bundle configuration](../configuration.md), [HTTP clients](../advanced/architecture/clients.md), [Middleware](../middleware.md) and [Errors](../errors.md). For scope and design rationale, see [Architecture](../architecture.md). The full index is [documentation.md](../documentation.md).
