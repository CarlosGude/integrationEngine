# Plan Status Update - 2026-09-16

## Phase 2 (Days 11-32) Status

### ✅ Completed (Days 11-16)

| Day | Task | Status | Notes |
|-----|------|--------|-------|
| 11 | D2.1 Bootstrap | ✅ | Symfony 7.4 + PSR-4 structure complete |
| 12 | D2.2 Docker/CI | ✅ | PHP-FPM + Nginx, GitHub Actions pipeline |
| 13 | D2.3 i18n/layout | ✅ | EN/ES routes, base template, homepage |
| 14 | D2.4 Tour motor | ✅ | TourRegistry, step navigation, YAML config |
| 15 | D2.5 Snippets | ✅ | Source extractor, syntax highlighting, security |
| 16 | D2.6 Tour UI | ✅ | TourController, TraceRecorderMiddleware, endpoints |

**Commit:** `d2bd230` (Day 17 work) + prior infrastructure commits

### 🔄 In Progress (Day 17)

**D2.7 TMDB: GetConfiguration + GetMovie**

**Status:** Blocked by HTTP transmission layer issue
- ✅ Symfony kernel initializes correctly
- ✅ Routing resolves (`/en/` → HomepageController)
- ✅ Template renders to 1571 bytes (verified via direct PHP execution)
- ❌ HTTP response via curl/Nginx: 0-5 bytes returned
- 🔍 Problem layer identified: PHP-FPM ↔ Nginx socket communication

**Configuration done:**
- TMDB API key stored in `.env.local`
- TMDB Bearer token stored in `.env.local`
- Credentials ready for mapper implementation once HTTP layer fixed

**Next session debugging plan (5 attempts, priority order):**
1. Check PHP-FPM timeout settings (max_request_terminate_timeout, etc.)
2. Test with PHP built-in server (bypass Nginx)
3. Inspect FastCGI packets with tcpdump on port 9000
4. Add Nginx debugging logs for FastCGI response
5. Disable chunked transfer encoding

**See:** [Full debugging checklist in memory](../.claude/projects/-Users-cgude-PhpstormProjects-integrationEngine/memory/day-17-http-transmission-debug.md)

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

---

## Phase 1 (Days 1-10) ✅

All bundle improvements (connection resolver, headers to mapper, request middleware, per-connection auth cache) completed in v4.1.1. Deployed to Packagist.

---

## Key Files Updated This Session

- `integrationEngine-demo/README.md` — Status, architecture, debugging plan
- `integrationEngine-demo/.env.local` — TMDB credentials (git-ignored)
- `integrationEngine-demo/memory/day-17-http-transmission-debug.md` — Detailed debug checklist
- `integrationEngine-demo/memory/MEMORY.md` — Index updated

---

## Timeline Expectation

**Assuming HTTP blocker resolves next session (< 1 hour):**
- Day 17: TMDB integration (2-3 hours)
- Day 18-20: Domain layer + tour step 1 (3-4 sessions)
- Day 21-32: Remaining integrations + deployment (12-14 sessions)
- **Estimated completion:** Late October 2026

**If HTTP blocker requires architectural change (FrankenPHP swap):**
- Add 2-3 hours; blocker resolution becomes Day 17b
- Timeline extends by ~1 week

---

## What Works Right Now

```bash
# From any terminal:
docker compose up --build -d

# Then inside container:
docker compose exec php php -r "
require 'vendor/autoload.php';
\$kernel = new \App\Kernel('dev', true);
\$request = \Symfony\Component\HttpFoundation\Request::create('/en/', 'GET');
\$response = \$kernel->handle(\$request);
echo \$response->getContent();  # 1571 bytes ✅
"

# But from localhost:8080/en/ → 0-5 bytes ❌
```

---

## Decisions This Session

1. **Removed Infection from prod build** — Symfony 7.4 incompatibility; tests still run locally
2. **Disabled Translation component** — Not needed for demo v1; can add later
3. **Conditional bundle loading** — WebProfilerBundle, DebugBundle only if installed
4. **Nginx buffering disabled** — To rule out truncation at output layer
5. **APP_ENV=dev for local compose** — Easier debugging; can flip to prod before VPS deployment
