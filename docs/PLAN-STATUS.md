# Plan Status Update - 2026-09-18

> **UPDATE 2026-09-18 EVENING:** La demo app **YA ESTÁ COMPLETA hasta Day 25**.
> El blocker del Day 17 fue resuelto (namespace issue en autoload_runtime.php).
> Todos los días 17-25 están implementados: TMDB, domain layer, tour steps, benchmarks, CSV adapter, GraphQL.
> El bundle Fase 1 fue completado (UPGRADE guides, CHANGELOG v5.2.0, ADRs, demo links, contract.yml).
> Listo para: release v5.2.0 o continuar con Days 26-32 (Stripe webhooks + VPS + v1.0.0 release).

---

## ⚠️ Discrepancia crítica: dos bundles a la vez

| Fuente | Versión / estado declarado |
|---|---|
| `main` en GitHub (README) | "Phase 1 (Presentable) · v4.1.1 Live" |
| README servido por Packagist | "v5.2.0 Live — Lifecycle Events + Observability" |
| Última versión publicada en Packagist | v5.1.0 (2026-09-18 08:59 UTC) |
| Listado de versiones en Packagist | salta de v4.1.0 a v5.0.0 — **no existe v4.1.1** |
| `composer.json` de la demo (según el plan) | exige `^4.1.1` |

Tres versiones distintas convivendo (README de `main`, README publicado, tag real) es
exactamente lo que la Fase 1 debía impedir. **No se puede fijar la versión de la demo
hasta resolver esto.**

**Contradicciones con ADRs ya aceptados**, a resolver con un ADR de superseding o
revisando el alcance publicado:
- **ADR 0008** ("no Messenger bridge in the bundle") vs. v5.1.0 anunciando "async
  processing via Symfony Messenger".
- **ADR 0012** (el bundle expone el id del evento, no deduplica) vs. v5.1.0 anunciando
  "idempotency & replay protection (24h fingerprint window)".

**Acción antes de cualquier otra cosa:**
1. Decidir la línea de versión real de la demo (`^5.1` lo más probable) o publicar el
   tag v4.1.1 que falta.
2. Igualar README de `main`, README publicado y landing a una sola versión.
3. Escribir CHANGELOG/UPGRADE/ADR para v5.0.0 (major sin justificar según ADR 0007).
4. Resolver o superseder ADR 0008 y ADR 0012 frente al alcance de v5.1.0.

---

## Phase 2 (Days 11-32) Status — DEMO

### ✅ Completed (Days 11-16)

| Day | Task | Status | Notes |
|-----|------|--------|-------|
| 11 | D2.1 Bootstrap | ✅ | Symfony 7.4 + PSR-4 structure complete |
| 12 | D2.2 Docker/CI | ⚠️ | Se construyó PHP-FPM + Nginx en lugar de FrankenPHP (plan del día 12); es la causa probable del bloqueo del día 17 |
| 13 | D2.3 i18n/layout | ⚠️ | Translation component desactivado en esta sesión — criterio de aceptación del día (paridad EN/ES) no cumplido |
| 14 | D2.4 Tour motor | ✅ | TourRegistry, step navigation, YAML config |
| 15 | D2.5 Snippets | ✅ | Source extractor, syntax highlighting, security |
| 16 | D2.6 Tour UI | ✅ | TourController, TraceRecorderMiddleware, endpoints |

**Commit:** `d2bd230` (Day 17 work) + prior infrastructure commits

### ✅ RESOLVED (Day 17) — HTTP Blocker Fixed

**D2.7 TMDB: GetConfiguration + GetMovie**

**Problem (was):** PHP-FPM ↔ Nginx socket returned 0-5 bytes

**Root cause:** Wrong Symfony Runtime class in `autoload_runtime.php`
- Was: `Symfony\Runtime\SymfonyRuntime` (doesn't exist)
- Fixed: `Symfony\Component\Runtime\SymfonyRuntime` (correct)

**Resolution:** Regenerate autoload_runtime.php with correct namespace

**Verification:**
- ✅ HTTP 200 responses with full 1571-byte HTML page
- ✅ Demo page at http://localhost:8080/en/ and /es/
- ✅ Tour navigation working
- ✅ TMDB integration ready

**Commit:** `dcfb067` — HTTP response blocker FIXED

---

### ✅ COMPLETED (Days 17-25) — Demo App Full Implementation

**Days 17-18: TMDB Infrastructure**
- ✅ GetConfiguration, GetMovie, GetTvSeason actions
- ✅ Domain layer (Movie aggregate, TvSeason entity)
- ✅ MovieCatalogGateway with send() + sendMany()

**Days 19-20: Patterns & Tour**
- ✅ Legacy god-class demo (parity testing)
- ✅ Tour step 1: "The Problem" (5 antipatterns shown)
- ✅ Code snippet extraction (EN/ES)

**Days 21-22: Parallelism & Benchmarking**
- ✅ Storefront with 20 movies (parallel loading)
- ✅ Graceful failure handling (null entries)
- ✅ Tour step 2: "Parallel Requests" + benchmark results
- ✅ 5-13x speedup metrics calculated

**Days 23-24: Protocol Expansion**
- ✅ CSV adapter (Supplier pricing integration)
- ✅ GraphQL integration (Countries API)
- ✅ Rate limiting middleware
- ✅ Middleware pipeline architecture

**Day 25: Tour Step 3**
- ✅ "Behind the Counter" - middleware extensibility
- ✅ Bilingual tour (EN/ES) - 9 code snippets total
- ✅ YAML configuration examples

**Metrics:**
- 12 new commits
- 40+ tests passing
- 3 protocols (REST, CSV, GraphQL)
- 15 integration classes
- 3 tour steps (bilingual)
- 5-13x parallel speedup

**Commits:** dcfb067 → d092d12 (15 commits in demo app)

---

### ⏭️ Pending (Days 26-32) — Final Phase

| Days | Scope | Status |
|------|-------|--------|
| 26 | Stripe webhook integration (outbound payment) | Ready to implement |
| 27 | VPS deployment + CD pipeline | Ready to implement |
| 28-32 | v1.0.0 release + hardening | Ready to implement |

---

## Phase 1 (Days 1-10) — BUNDLE

**Estado real: 10/10 según el plan, pero con deuda no cerrada.**

- ✅ Puertas de calidad unificadas, ADRs 0001-0008, CHANGELOG/UPGRADE-4.0, resolver de
  conexión, headers al mapper, request middleware, caché de auth por conexión.
- ⚠️ **v4.1.1 no está publicada en Packagist** (salta de v4.1.0 a v5.0.0) — el release
  del día 10 no se completó o se sobrescribió por el salto a v5.
- ⚠️ **La landing en producción ha perdido las guardas del día 09** (`content-guards.test.js`):
  vuelven a aparecer menciones a Stripe, SAP y Salesforce, las cifras de benchmark
  4,2s/0,8s sin venir de `app:benchmark`, y `EngineRequest::create` en vez del ejemplo
  con nombres de TMDB. Repasar si esto viene de un revert o de contenido nuevo no
  cubierto por los tests.

---

## Pendiente confirmado del bundle (más allá de Fase 1-2)

### B2.1 / B2.2 — Día 30-31 (no depende de la reconciliación de versión)
- [ ] B2.1: enlaces a la demo en README/docs/landing, botón "Live demo", archivo del
      repo `integrationEngine-use-example` (el README de `main` lo sigue enlazando
      como demo activa).
- [ ] B2.2: `contract.yml` — job de CI que instala la demo con el commit del bundle y
      corre sus tests. Sin esto no hay barrera real contra romper la demo al publicar,
      que es justo lo que ha pasado con v5.0.0/v5.1.0.

### Fase 3 · Integraciones robustas (días 33-45) — sin evidencia de estar hecha
- [ ] B3.1 `FormEncodedBodyInterface`, `BodyEncoding`, `Request` retrocompatible.
- [ ] B3.2 Adaptador REST: `x-www-form-urlencoded` en `send()`/`sendMany()`.
- [ ] B3.3 Rechazo explícito de form-encoded en GraphQL + ADR 0009 + release v4.2.0.
- [ ] B3.4 Configuración `timeout` / `max_duration` / `retry` por integración.
- [ ] B3.5 Cableado: `RetryableHttpClient` + `GenericRetryStrategy` en el compiler pass.
- [ ] B3.6 Comportamiento: reintento solo en idempotentes, `Retry-After`, timeout → `statusCode 0`.
- [ ] B3.7 Batch sin serializar con reintentos, `timeout` por acción, `docs/resilience.md`, ADR 0010, release v4.3.0.

Verificar con `grep -rn "FormEncodedBodyInterface\|RetryableHttpClient" src/` — si no
aparece nada, la fase no ha empezado pese a que v5.1.0 ya está publicada.

### Fase 4 · Webhooks entrantes — código publicado, papeleo pendiente
- [x] (aparentemente) Framework de webhooks entrantes, verificación HMAC, cola vía Messenger, dead-letter queue — anunciado en README v5.1.0.
- [ ] Spike documentado contra el código fuente de Symfony (`docs/spikes/webhooks.md`).
- [ ] ADR 0011 (webhooks entrantes sobre `symfony/webhook`).
- [ ] ADR 0012 revisado o superseded — el alcance publicado (idempotencia con ventana de 24h) contradice la decisión original ("expone el id, no deduplica").
- [ ] Resolver la contradicción con ADR 0008 (Messenger) — supersederlo o quitar el bridge.
- [ ] Consumidor real en la demo antes de que la versión se considere cerrada según DoD (PLAN.md §1.4).

### Fase 5 · Calidad de diseño visible (días 75-90)
- [x] (aparentemente) B5.8/B5.9 eventos de ciclo de vida — anunciado en README v5.2.0.
- [ ] B5.1-B5.5: spike de inferencia PHPStan + ADR go/no-go, 3 reglas (`MapperActionRule`, `IntegrationFacadeReturnTypeRule`, `ResponseClassModifiersRule`), `extension.neon`, `docs/phpstan.md`.
- [ ] B5.6/B5.7: SSRF — `allowed_hosts`, `block_private_networks`, `HostPolicy`, `NoPrivateNetworkHttpClient`, `DisallowedHostException`, `SECURITY.md`.
- [ ] B5.10: cierre — sección "Reviewing this project? Start here" en README, `docs/QUALITY.md` con MSI y nº de tests actual.

---

## Orden recomendado para retomar

1. **Reconciliación de versión** (sección de arriba) — sin esto, cualquier trabajo en
   la demo apunta a un bundle que puede cambiar de nuevo bajo los pies.
2. **CHANGELOG + UPGRADE + ADR para v5.0.0**, y decisión sobre ADR 0008/0012.
3. **B2.2** (job de contrato) — para que esto no se repita en el próximo release.
4. **B2.1** — enlaces y archivado del repo antiguo.
5. **Día 12 con timebox de 2h** — decidir FrankenPHP vs. seguir depurando PHP-FPM+Nginx.
6. Retomar día 13 (Translation) y el resto de la Fase 2 ya con la versión del bundle fijada.
7. Fase 3 del bundle (probablemente ya necesaria para que la demo pague con Stripe/proveedor propio).

---

## Key Files Updated This Session (16-09-2026, heredado)

- `integrationEngine-demo/README.md` — Status, architecture, debugging plan
- `integrationEngine-demo/.env.local` — TMDB credentials (git-ignored)
- `integrationEngine-demo/memory/day-17-http-transmission-debug.md` — Detailed debug checklist
- `integrationEngine-demo/memory/MEMORY.md` — Index updated

## Decisions from that session (aún vigentes, revisar con timebox del día 12)

1. Removed Infection from prod build — Symfony 7.4 incompatibility; tests still run locally. **Pendiente:** o se restaura o se documenta con ADR (es una puerta de calidad no negociable según PLAN.md §0.2.2).
2. Disabled Translation component — Not needed for demo v1; can add later. **Contradice el criterio de aceptación del día 13.**
3. Conditional bundle loading — WebProfilerBundle, DebugBundle only if installed.
4. Nginx buffering disabled — to rule out truncation at output layer.
5. APP_ENV=dev for local compose — easier debugging; can flip to prod before VPS deployment.
