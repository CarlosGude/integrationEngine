# IntegrationEngine Documentation

Complete guide to understanding, using, and extending IntegrationEngine.

## 🚀 Quick Start

**New to IntegrationEngine?** Start with [getting-started/](./getting-started/)

- [Getting Started Overview](./getting-started/)
- [Building Your First Action](./getting-started/actions.md)
- [Authentication](./getting-started/authorization.md)

## 📚 Full Documentation

### Beginner
- **[getting-started/](./getting-started/)** — Concepts and quick starts
  - Actions, authorization, batch requests, mappers, context resolution

### Advanced
- **[advanced/](./advanced/)** — Architecture, design, operational concerns
  - Class hierarchy, client adapters, debugging, quality gates

### Architecture Decisions
- **[adr/](./adr/)** — Architecture Decision Records
  - Rationale for key design choices (8 documented decisions)

### Languages
- **[es/](./es/)** — Documentación en español

### Historical
- **[archived/](./archived/)** — Research notes and spikes

## 🗂️ Structure

```
docs/
├─ README.md (you are here)
├─ getting-started/       ← Start here
│  ├─ actions.md
│  ├─ authorization.md
│  ├─ batch-requests.md
│  ├─ context-and-path.md
│  └─ mappers-and-responses.md
├─ advanced/
│  ├─ architecture/       ← System design
│  ├─ debugging.md
│  ├─ QUALITY.md
│  └─ AI-AGENT-USAGE.md
├─ adr/                   ← Architecture Decision Records
├─ es/                    ← Spanish documentation
└─ archived/              ← Historical research
```

## 🔗 Related Documentation

- **[../README.md](../README.md)** — Project intro and quick reference
- **[../CLAUDE.md](../CLAUDE.md)** — Developer workflow and standards
- **[../ARCHITECTURE.md](../ARCHITECTURE.md)** — Design decisions and rationale
- **[../TESTING.md](../TESTING.md)** — Test strategy and patterns
- **[../CONTRIBUTING.md](../CONTRIBUTING.md)** — How to contribute

## 📖 Learning Paths

### I want to build an integration
1. Read [getting-started/actions.md](./getting-started/actions.md)
2. Check [getting-started/authorization.md](./getting-started/authorization.md) for auth
3. See [getting-started/mappers-and-responses.md](./getting-started/mappers-and-responses.md) for response handling

### I want to understand the design
1. Read [../ARCHITECTURE.md](../ARCHITECTURE.md)
2. Check [advanced/architecture/class-graph.md](./advanced/architecture/class-graph.md)
3. Review [adr/](./adr/) for decisions

### I want to debug or optimize
1. See [advanced/debugging.md](./advanced/debugging.md)
2. Check [advanced/QUALITY.md](./advanced/QUALITY.md) for testing
3. Review [../OBSERVABILITY.md](../OBSERVABILITY.md)

### I'm using AI agents with this
1. Read [advanced/AI-AGENT-USAGE.md](./advanced/AI-AGENT-USAGE.md)
2. See [../CLAUDE.md](../CLAUDE.md) for developer instructions

---

**Last updated:** 2026-09-18  
**Version:** See [../CHANGELOG.md](../CHANGELOG.md)
