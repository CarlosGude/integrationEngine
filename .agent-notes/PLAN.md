# IntegrationEngine Marketing Campaign - Days 1-90

Generated: 2026-09-17 | Phases: 1 (Marketing prep), 2 (Demo Symfony app)

---

## Phase 1: Marketing & Bundle Improvements (Days 1-10) ✅

### ✅ v4.0 Bundle Features

- [x] Connection resolver (multi-tenant runtime resolution)
- [x] Headers passed to mappers (response introspection)
- [x] Request middleware (OAuth signing, pre-resolution concerns)
- [x] Per-connection auth token cache (distinct tokens per connection)
- [x] Path resolution from request body (before context)

**Verification:** Tests: 489 passing | Stan: 0 errors | Mutation MSI: 100%

### ✅ v4.1 Docs & Marketing Infrastructure

- [x] Architecture.md (comprehensive bundle guide)
- [x] TESTING.md (strategy and patterns)
- [x] CONTRIBUTING.md (developer workflow)
- [x] Docs/ADR/ (8 decision records)
- [x] Landing page skeleton (12-week article pipeline)

**Verification:** Documentation links valid | README status block present

---

## Phase 2: Demo Application (Days 11-32)

### ✅ Days 11-16: Foundation Complete

- [x] D11: Symfony 7.4 bootstrap + PSR-4 structure
- [x] D12: Docker/CI pipeline (PHP-FPM + Nginx + GitHub Actions)
- [x] D13: i18n layout (EN/ES routes, base template)
- [x] D14: Tour motor (TourRegistry, YAML config, step navigation)
- [x] D15: Snippet system (source extractor, syntax highlighting, security)
- [x] D16: Tour UI (controller, middleware, endpoints)

**Verification:** Docker compose running | Symfony kernel initializing | Routes resolving

### ✅ Days 17-18: TMDB Integration Complete

**D17: TMDB GetConfiguration + GetMovie**
- [x] HTTP transmission blocker resolved (PHP-FPM/Nginx socket issue)
- [x] TMDB API credentials configured (.env.local)
- [x] GetConfiguration action + mapper
- [x] GetMovie action + mapper (with pagination support)
- [x] 3 mapper tests passing

**D18: Domain Layer & Gateway**
- [x] Movie domain entity (immutable value object)
- [x] TvSeason domain entity (for storefront cross-sell)
- [x] MovieCatalogGateway (app-owned port, DTO → domain translation)
- [x] Deptrac architecture validated (no bundle → domain leakage)

**Verification:** `make test` (489 tests) | `make stan` (0 errors) | `make mutation` (MSI 100%)

### ⏳ Days 19-32: Remaining Integrations & Deployment

| Days | Scope | Status |
|------|-------|--------|
| 19 | Legacy god-class demo (parity tests) | Pending |
| 20 | Tour step 1: "The Problem" (before/after comparison) | Pending |
| 21 | Storefront (parallel requests) | Pending |
| 22 | Tour step 2: "Parallel Requests" + benchmark | Pending |
| 23 | CSV provider (custom client adapter) | Pending |
| 24 | GraphQL integration + RateLimitMiddleware | Pending |
| 25 | Tour step 3: "Behind the Counter" (architecture walkthrough) | Pending |
| 26-27 | Hardening & security audit | Pending |
| 28-29 | VPS setup & CD pipeline | Pending |
| 30-32 | v1.0.0 release, blog launch, marketing push | Pending |

---

## Checkpoint: Days 16-18

- **Code quality:** All Quality Checks Passing
  - Code Style: ✅ PHP-CS-Fixer (0 violations)
  - Static Analysis: ✅ PHPStan level max (0 errors)
  - Tests: ✅ 489 tests (1284 assertions)
  - Mutations: ✅ 578 killed (MSI 100%, Covered Code MSI 100%)

- **Deliverables:**
  - TMDB GetConfiguration/Movie/TvSeason actions + mappers
  - Movie domain entity + MovieCatalogGateway
  - HTTP layer debugged & operational
  - Demo app live at localhost:8080

- **Blockers resolved:** HTTP transmission layer (PHP-FPM socket)
- **Next session:** Day 19 (legacy god-class demo for comparison)

---

## Notes

- Demo app repository: `/Users/cgude/PhpstormProjects/integrationEngine-demo`
- Bundle repository: `/Users/cgude/PhpstormProjects/integrationEngine`
- Marketing roadmap: 12-week article pipeline (Dev.to + LinkedIn)
- Timeline: Estimated completion late October 2026 (assuming no architectural changes)
