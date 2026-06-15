# Landing Page — Claude Instructions

## Stack

Cloudflare Worker (`wrangler dev`). All source in `landing/src/`:

- `html.js` — page structure and section order
- `css.js` — all styles as a template literal
- `client.js` — inline JS (toggle, nav, copy, scroll)
- `i18n/en.js` and `i18n/es.js` — all copy, both languages must stay in sync

Start dev server:
```bash
npx wrangler dev --config landing/wrangler.toml --port 8788
```

If port is busy: `lsof -ti :8788 | xargs kill -9`

## Audience Review Standard

Every time a landing page review is requested, run **3 passes per persona** and report the average. Use a **1–100 scale** where 100 = the author's criterion.

**Minimum acceptable score: 75 for every persona.**

### Personas and target scores (in priority order)

| Priority | Persona | Target | Floor |
|----------|---------|--------|-------|
| 1 | Arquitecto | 88–92 | 80 |
| 2 | Middle dev (3–7 yrs) | 83–88 | 78 |
| 3 | CTO | 80–85 | 75 |
| 4 | Junior dev | 77–82 | 75 |
| 5 | CEO | 75–80 | 75 |

Scores should decrease in that order. If a lower-priority persona scores higher than a higher-priority one, flag it as a balance problem.

### What each persona needs to take away

**Arquitecto** — Design patterns, separation of concerns, interfaces, extension points, testability. Must see: action/mapper/response split, YAML contract, runtime invariants, how to extend.

**Middle dev** — DX, concrete code, "will this complicate my life?", scaffolding, testing strategy. Must see: real code examples, `make:integration` command, docs link.

**CTO** — Team standards, incremental adoption, ROI, risk. Must see: no big-bang rewrite, team consistency via generator, parallel execution ROI, Symfony-native (low risk).

**Junior dev** — What is this, how do I start, what does it generate. Must see: simple "what is this" framing, directory structure or file list after scaffolding, 3-step get started.

**CEO** — Business value, team productivity, cost of technical debt. Must see: business-language framing (not just developer jargon), time/cost efficiency signal.

### Review format

```
## Audience Review

### [Persona]
*[One-line framing of what this persona cares about]*

| Pass | Score | Notes |
|------|-------|-------|
| 1    | XX    | ...   |
| 2    | XX    | ...   |
| 3    | XX    | ...   |

**Average: XX/100**
**Gap:** [Most important missing element for this persona]

---
[repeat for each persona]

## Summary

| Persona    | Score | Status        |
|------------|-------|---------------|
| Arquitecto | XX    | ✓ / ⚠ / ✗    |
| Middle dev | XX    | ✓ / ⚠ / ✗    |
| CTO        | XX    | ✓ / ⚠ / ✗    |
| Junior     | XX    | ✓ / ⚠ / ✗    |
| CEO        | XX    | ✓ / ⚠ / ✗    |

✓ = at or above target  ⚠ = above floor but below target  ✗ = below floor (must fix)

**Balance check:** [Flag if priority order is violated]
**Most impactful fix:** [Single highest-ROI change to raise the lowest score]
```
