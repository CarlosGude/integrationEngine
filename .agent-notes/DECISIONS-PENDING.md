# Decisiones Pendientes

Generated: 2026-09-17 | Scope: Days 19-32, v1.0.0 release

---

## High Priority (Block Release)

### 1. Storefront Architecture: Single vs. Parallel Requests
**Context:** Day 21 requires building a storefront showing 3 movie cards.  
**Decision:** Should MovieCatalogGateway execute 3 `getMovie()` calls sequentially or use `engine→sendMany()`?

**Option A: Sequential (safe, simpler)**
```php
$movies = [];
foreach ($ids as $id) {
    $movies[] = $this->gateway->getMovie($id); // 3 serial HTTP calls
}
```
**Pros:** No batch logic, easy to debug  
**Cons:** ~3s latency (1s per call), doesn't showcase bundle capability

**Option B: Parallel (performant, advanced)**
```php
$responses = $this->engine->sendMany([
    'movie_1' => new EngineRequest($getMovieAction, $context1, ...),
    'movie_2' => new EngineRequest($getMovieAction, $context2, ...),
    'movie_3' => new EngineRequest($getMovieAction, $context3, ...),
]);
$movies = $this->gateway->consolidate($responses);
```
**Pros:** ~1s latency (all parallel), demonstrates batch power, Tour step 2 impact  
**Cons:** More boilerplate, requires AbstractBatchMapper

**Recommendation:** Option B (parallel) — it's the marketing hook for Days 21-22.  
**Impact:** Adds 1-2 hours to Day 21, but enables Day 22 benchmark.  
**Status:** Ready to implement, no blockers.

---

### 2. Tour Step Content Strategy: Technical vs. Narrative
**Context:** Days 20, 22, 25 require tour content (three core messages).  
**Decision:** Should tour steps be technical deep-dives or business-friendly narratives?

**Option A: Technical (for developer audience)**
- Step 1: "The Problem" → detailed before/after code comparison
- Step 2: "Parallel Requests" → concrete benchmark (1s vs. 3s) + latency graphs
- Step 3: "Behind the Counter" → architecture diagram (layers, middleware stack)

**Option B: Narrative (for DevRel/CTOs)**
- Step 1: "Scaling API Integrations" → business value (time-to-market, maintainability)
- Step 2: "Concurrent Requests" → real-world scenario (storefront, multi-fetch)
- Step 3: "Clean Architecture" → philosophy (separation of concerns, testability)

**Recommendation:** Hybrid (85% technical, 15% narrative).  
- Narrative hook at top (why it matters)
- Technical content in body (code, graphs, architecture)
- CTA at bottom (link to blog posts)

**Impact:** Shapes writing/design work for Days 20, 22, 25.  
**Status:** Requires Carlos approval before starting Day 20.

---

### 3. Blog/Marketing Sequencing: When to Publish First Article
**Context:** Marketing plan is 12-week campaign (Dev.to + LinkedIn).  
**Decision:** Publish Day 19 post (legacy god-class demo) before or after demo v1.0?

**Option A: Publish Day 19 post immediately (early traction)**
- "The Problem: Why Most API Integration Code is a Mess"
- Use real code examples (god-class pattern)
- Drive traffic to repo early
- Pros: Early engagement, longer SEO ramp-up
- Cons: Demo not live yet, can't show solution live

**Option B: Wait until Day 30+ (full context)**
- Publish series as cohesive narrative
- Full solution live (blog → demo)
- Stronger conversion (readers can try it)
- Pros: Higher conversion, more polished
- Cons: Late traffic, competitors move faster

**Recommendation:** Option A (publish Day 19 post early).  
- Establishes "problem" before solution
- SEO compounding over 6+ weeks
- Traffic baseline before demo launch
- Series can pivot based on engagement

**Impact:** Requires blog post draft during Days 19-20.  
**Status:** Requires decision + Carlos oversight.

---

## Medium Priority (Schedule Planning)

### 4. CSV Provider Implementation: Scope Creep Risk
**Context:** Day 23 (CSV custom adapter) is a stretch goal, not core to demo.  
**Decision:** Include or defer to v5.0?

**Current scope (Day 23):**
- Custom ClientInterface implementation (instead of HTTP)
- FileReaderAdapter reading CSV lines
- ActionContext iterating rows
- Mapper parsing columns to Movie domain

**Risk factors:**
- Adds architectural complexity (custom adapter pattern)
- Not showcased in tour (Days 20, 22, 25 are HTTP-only)
- Could slip if Days 19-22 overrun

**Recommendation:** Keep Day 23 in plan, but flag as "stretchable."  
- If Days 19-22 on track: implement Day 23
- If any slip: defer to v5.0 (post-launch marketing phase)
- CSV is powerful for "extensibility" demo (Part 4 of blog series)

**Status:** Conditional, revisit after Day 22.

---

### 5. GraphQL Integration: Design Complexity
**Context:** Day 24 adds GraphQL adapter + RateLimitMiddleware.  
**Decision:** Scope: Full query builder or simplified GraphQL client?

**Option A: Simplified (use SymfonyGraphQLClientAdapter)**
```php
class GetDirectorAction extends AbstractAction {
    public function getBody(): ?string {
        return <<<GRAPHQL
            query { director(id: "{id}") { name, films { id, title } } }
        GRAPHQL;
    }
}
```
- Reuses existing adapter (no new code)
- Simple action + mapper
- Limited to basic queries
- Time: ~2 hours

**Option B: Full (custom query builder)**
```php
$query = GraphQL::query()
    ->select('director')
    ->field('name')
    ->field('films', ['limit' => 10])
    ->build();
```
- Flexible, type-safe
- Higher complexity
- Overkill for demo
- Time: ~5 hours (plus test coverage)

**Recommendation:** Option A (simplified).  
- Demonstrates adapter pattern (GraphQL vs. REST)
- Keeps Day 24 on schedule (4h)
- Sufficient for blog/marketing (query examples)

**Status:** Locked, ready to implement.

---

## Low Priority (Post-Launch)

### 6. Security Audit Scope (Days 26-27)
**Areas to review:**
- ✅ Request middleware (no secrets leaking to logs)
- ✅ Cache namespacing (token isolation per connection)
- ✅ Error response handling (no sensitive data exposed)
- ⏳ Rate limiting (middleware added Day 24)
- ⏳ Input validation (ActionContext + placeholders)
- ⏳ CORS/CSP headers (if demo adds API endpoints)

**Decision:** External security audit or self-review?  
**Recommendation:** Self-review (Carlos + Claude).  
- Sufficient for v1.0 demo
- External audit can be Post-Launch hardening (Q1 2027)

---

### 7. VPS Setup (Days 28-29)
**Pending decisions:**
- VPS provider (Hetzner? DigitalOcean? AWS?)
- Container orchestration (Docker Compose vs. K8s?)
- CI/CD pipeline (GitHub Actions → VPS deploy?)
- Domain name (demo.integrationengine.dev? blog.integrationengine.dev?)
- SSL certificate (Let's Encrypt via Certbot)

**Recommendation:** Defer to Day 28 kickoff.  
**Status:** Carlos to decide infrastructure by end of Day 25.

---

### 8. v1.0.0 Release Ceremony (Days 30-32)
**Scope:**
- GitHub release notes (comprehensive, link to blog)
- Packagist update (if bundle version bumped)
- Blog post summary (launch announcement)
- Social media push (LinkedIn + Dev.to cross-posting)
- Demo app tagged v1.0.0

**Decision:** Automate release notes from commits or manual?  
**Recommendation:** Manual (Carlos writes compelling narrative, Claude polishes).  
**Time estimate:** 4 hours total.

---

## Blocked or Deferred

### (None currently)

All Days 19-32 are unblocked and ready to start.

---

## Session Checkpoints

These decisions should be revisited:

- **After Day 19:** Confirm storefront approach (serial vs. parallel)
- **After Day 20:** Validate tour content strategy with real post
- **After Day 22:** Confirm CSV scope inclusion (Day 23)
- **After Day 25:** Lock infrastructure decisions (Days 28-29)
- **Day 30:** Kickoff release ceremony

---

## Notes for Carlos

- Marketing sequencing (early blog post) is aggressive but recommended for SEO
- Keep Day 23 (CSV) as stretchable goal — don't sacrifice schedule for scope
- GraphQL Day 24 is simplified by design (not a full implementation)
- Days 26-32 are lower-risk (cleanup, deployment, launch) — buffer built-in

---

## Appendix: Decision Log

| Date | Decision | Status |
|------|----------|--------|
| 2026-09-17 | Fix HTTP blocker (PHP-FPM timeout) | ✅ Implemented |
| 2026-09-17 | TMDB actions + mappers | ✅ Implemented |
| 2026-09-17 | Domain layer + gateway pattern | ✅ Implemented |
| 2026-09-17 | Storefront: serial vs. parallel? | 🤔 Pending approval |
| 2026-09-17 | Tour content: technical vs. narrative? | 🤔 Pending approval |
| 2026-09-17 | Blog post timing: early or late? | 🤔 Pending approval |
| 2026-09-17 | CSV Day 23: include or defer? | 🤔 Conditional |
| 2026-09-17 | GraphQL Day 24: simplified or full? | ✅ Simplified (locked) |

---

*Generated automatically during session close. Review and approve decisions before Day 19 kickoff.*
