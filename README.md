# IntegrationEngine

External integrations tend to rot in Symfony projects.

Every API becomes a different shape, a different structure, a different way of thinking.
After a few months, you no longer have integrations. You have a zoo.

IntegrationEngine forces every integration to look the same.

---

## 🧠 Core idea

An integration is not a client.

It is a collection of predictable endpoints.

Every endpoint has exactly two responsibilities:

- Request (what goes in)
- Response (what comes out)

Nothing else is allowed to sprawl.

---

## 🚀 What this solves

IntegrationEngine removes:

- Inconsistent API clients across services
- Ad-hoc HTTP logic scattered in services
- Repeated mapping boilerplate per endpoint
- “How does this API work again?” moments
- Integration archaeology after months

---

## ⚡ Quick usage

```php
$employee = $dummyRestApi->getEmployee(123);
```

No HTTP clients.
No request builders.
No mappers.

Just integrations.

---

## 🧱 Structure

Each integration follows the same predictable structure:

```
Integration
 ├── Endpoint
 │     ├── Request
 │     │     ├── Action
 │     │     └── Context
 │     └── Response
 │           ├── Mapper
 │           └── DTO
```

If you know one integration, you know them all.

---

## 🛠️ Installation

```bash
composer require carlosgude/integration-engine
```

---

## 🧪 Why it exists

Because external APIs are not the problem.

The problem is inconsistency between them.

---

## ⚠️ When NOT to use it

- You only have 1–2 simple API calls
- You need full low-level HTTP control everywhere
- You don’t want enforced structure in your codebase
