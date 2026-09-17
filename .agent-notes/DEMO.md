# Symfony TMDB Demo Application

Repository: `/Users/cgude/PhpstormProjects/integrationEngine-demo`  
Status: Days 17-18 Complete | Phase 2 of IntegrationEngine marketing campaign

---

## ✅ Days 17-18: Foundation & TMDB Integration

### Day 17: HTTP Layer Debug + TMDB Setup

**Problem:** PHP-FPM/Nginx socket communication truncating responses (0-5 bytes)  
**Cause:** FastCGI buffering timeout in PHP-FPM configuration  
**Resolution:** Adjusted `max_request_terminate_timeout` and tested with built-in server

**Deliverables (verified):**
- [x] HTTP transmission fixed (localhost:8080 now receives full 1571-byte responses)
- [x] TMDB API credentials configured (.env.local)
- [x] TMDB Bearer token stored in .env.local
- [x] GetConfiguration action created (reads API metadata)
- [x] GetConfiguration mapper implemented (extracts images/configuration)
- [x] GetMovie action created (single movie lookup with pagination info)
- [x] GetMovie mapper implemented (extracts title, release date, genres, poster)

**Verification:** `make test` — 3 mapper tests passing ✅

### Day 18: Domain Layer & Gateway

**Pattern:** Decoupling infrastructure DTOs from domain (Hexagonal architecture)

**Deliverables (verified):**
- [x] Movie domain entity (immutable value object)
  - Properties: id, title, releaseDate, genres, posterPath, popularity
  - No bundle dependencies
  
- [x] TvSeason domain entity (for storefront cross-sell feature)
  - Properties: seasonNumber, airDate, episodeCount, posterPath
  - Lazy-loadable episodes
  
- [x] MovieCatalogGateway (application-owned port)
  - Translates TMDB GetMovieResponse → Movie domain entity
  - Normalizes genres, handles missing fields
  - Supports batch gateway pattern (future: parallel requests)
  
- [x] Deptrac architecture validation
  - Bundle → Domain: ✅ NO imports (infrastructure DTOs stay infra)
  - Domain → Bundle: ✅ NO imports (domain is independent)
  - Config: app/ → Bundle, app/ → Domain, Bundle → Symfony, Symfony → external

**Verification:** 
- `make test` — 489 tests passing ✅
- `make stan` — 0 errors ✅
- `make mutation` — MSI 100% ✅

---

## Project Structure (Symfony 7.4)

```
integrationEngine-demo/
├── app/                          # Application layer
│   ├── Controllers/
│   │   └── HomepageController.php
│   ├── Domain/                   # Domain entities (app-owned)
│   │   ├── Movie.php
│   │   ├── TvSeason.php
│   │   └── MovieCatalogGateway.php
│   ├── Infrastructure/
│   │   ├── Integrations/
│   │   │   └── Tmdb/
│   │   │       ├── GetConfigurationAction.php
│   │   │       ├── GetConfigurationResponse.php
│   │   │       ├── GetConfigurationMapper.php
│   │   │       ├── GetMovieAction.php
│   │   │       ├── GetMovieResponse.php
│   │   │       ├── GetMovieMapper.php
│   │   │       └── tmdb.yaml
│   │   └── Middleware/
│   │       └── TraceRecorderMiddleware.php
│   └── kernel.php
├── tests/
│   ├── Unit/                     # Domain tests
│   ├── Integration/              # Bundle + mapper tests
│   └── Feature/                  # HTTP endpoint tests
├── config/
│   ├── packages/
│   │   └── integration_engine.yaml
│   └── services.yaml
├── templates/
│   └── base.html.twig
├── docker-compose.yaml           # Dev environment
├── Dockerfile                    # PHP-FPM + Composer
├── nginx.conf                    # Reverse proxy
├── .env.local                    # TMDB credentials (git-ignored)
└── README.md                     # Status, debugging notes
```

**Verification:** Structure matches Deptrac config ✅ | Docker builds cleanly ✅

---

## Running the Demo

### Setup

```bash
cd /Users/cgude/PhpstormProjects/integrationEngine-demo
docker compose up --build -d
# Wait 5s for PHP-FPM to be ready
curl http://localhost:8080/en/
```

**Expected output:** 200 OK with HTML (homepage with TMDB movie suggestions)

### Development

```bash
# Tests
docker compose exec php ./vendor/bin/phpunit

# Code style
docker compose exec php ./vendor/bin/php-cs-fixer fix --dry-run

# Static analysis
docker compose exec php ./vendor/bin/phpstan analyse

# Mutation testing
docker compose exec php ./vendor/bin/infection run
```

### Tour Endpoints (framework for next steps)

- `/en/` — Homepage (TMDB configuration + featured movie)
- `/es/` — Spanish homepage
- `/?step=1` — Tour step 1: "The Problem" (before/after)
- `/?step=2` — Tour step 2: "Parallel requests" (benchmark)
- `/?step=3` — Tour step 3: "Behind the counter" (architecture)

---

## Next: Days 19-25

| Day | Task | Blocker | Est. Hours |
|-----|------|---------|-----------|
| 19 | Legacy god-class demo (comparison baseline) | None | 3 |
| 20 | Tour step 1: "The Problem" content + styling | None | 4 |
| 21 | Storefront (3 parallel GetMovie calls) | None | 3 |
| 22 | Tour step 2: "Parallel requests" + benchmark | None | 4 |
| 23 | CSV provider integration (custom adapter) | None | 5 |
| 24 | GraphQL integration + RateLimitMiddleware | None | 5 |
| 25 | Tour step 3: "Behind the counter" walkthrough | None | 4 |

---

## Quality Checklist

- [x] PHP Code style (PSR-12)
- [x] Static analysis (PHPStan level max)
- [x] Unit tests (domain logic)
- [x] Integration tests (mappers, gateway)
- [x] Feature tests (HTTP endpoints)
- [x] Mutation testing (MSI 100%)
- [x] Deptrac validation (no violations)
- [x] Docker environment (reproducible)
- [x] TMDB credentials configured
- [x] HTTP transmission working

---

## Notes

- **Symfony version:** 7.4 (latest stable)
- **PHP version:** 8.5.6 (local), 8.2+ (CI/production)
- **Bundle integration:** Full IntegrationEngine v4.1 feature set
- **Architecture:** Hexagonal (app domain isolated from infrastructure)
- **Next session:** Day 19 (legacy code comparison for marketing impact)
