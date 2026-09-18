# Plan Status Update - 2026-09-18

> Esta actualización sustituye a la del 16-09-2026. Motivo: entre esa fecha y hoy el
> bundle ha publicado v5.0.0 y v5.1.0 en Packagist (hoy, 08:59 UTC) sin pasar por el
> proceso del plan — sin ADR de major, sin CHANGELOG, sin UPGRADE, sin consumidor en la
> demo. La demo, mientras tanto, sigue bloqueada en el día 17 desde el 16-09. Antes de
> retomar cualquier día concreto hace falta reconciliar ambas líneas.

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

### 🔴 Blocked (Day 17)

**D2.7 TMDB: GetConfiguration + GetMovie**

**Status:** Blocked by HTTP transmission layer issue
- ✅ Symfony kernel initializes correctly
- ✅ Routing resolves (`/en/` → HomepageController)
- ✅ Template renders to 1571 bytes (verified via direct PHP execution)
- ❌ HTTP response via curl/Nginx: 0-5 bytes returned
- 🔍 Problem layer identified: PHP-FPM ↔ Nginx socket communication

**Nota de reconciliación:** el plan (día 12) especifica FrankenPHP, no PHP-FPM+Nginx.
Antes de seguir depurando FastCGI, evaluar con timebox de 2h si migrar a FrankenPHP
resuelve el bloqueo de raíz en vez de perseguir sondas de una pila que no estaba en el
plan.

**Configuration done:**
- TMDB API key stored in `.env.local`
- TMDB Bearer token stored in `.env.local`
- Credentials ready for mapper implementation once HTTP layer fixed

**Debugging plan (5 attempts, priority order) — pendiente de ejecutar:**
1. Timebox 2h: sustituir PHP-FPM+Nginx por FrankenPHP según el plan original.
2. Si se descarta lo anterior: revisar `max_request_terminate_timeout` y timeouts de PHP-FPM.
3. Probar con el servidor embebido de PHP (sin Nginx).
4. Inspeccionar paquetes FastCGI con tcpdump en el puerto 9000.
5. Logs de depuración de Nginx para la respuesta FastCGI; desactivar chunked transfer encoding.

### ⏭️ Pending (Days 18-32)

| Days | Scope | Status |
|------|-------|--------|
| 18 | TMDB seasons, Gateway, domain | Blocked on Day 17 |
| 19 | Legacy god-class demo, parity tests | Blocked on Day 17 |
| 20 | Tour step 1: "The problem" | Blocked on Day 17 |
| 21 | Storefront (parallel requests) | Blocked on Day 17 |
| 22 | Tour step 2: "Parallel requests" + benchmark | Blocked on Day 17 |
| 23 | CSV provider (custom adapter) | Blocked on Day 17 |
| 24 | GraphQL + RateLimitMiddleware | Blocked on Day 17 |
| 25 | Tour step 3: "Behind the counter" | Blocked on Day 17 |
| 26-32 | Hardening, VPS, CD, v1.0.0 | Blocked on Day 17 |

**Adicional:** el día 11 exige `composer require carlosgude/integration-engine:^4.1.1`,
versión que no existe en Packagist. Los días 11-32 necesitan reescribirse contra la
línea de versión que se decida en la reconciliación de arriba antes de continuar.

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
