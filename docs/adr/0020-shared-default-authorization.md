# 0020 · Shared `defaults.authorization` with per-action override

- **Status:** Accepted
- **Date:** 2026-10-14

## Context

Dynamic authorization (`type: dynamic`) requires every protected action to declare the full block: `action`, `token_field`, `ttl`, and optionally `header`/`prefix`. In practice, one token action protects most or all endpoints of an integration, so the same five-line block is copy-pasted onto every action in the YAML file. The only reason the current format allows per-action divergence (a different `ttl`, a different token action entirely) is that nothing is shared — but the common case pays the full verbosity cost of the rare case.

## Decision

**Add an optional `defaults:` section to the integration YAML, read by `YamlConfigAdapter` alongside the existing reserved `webhooks:` section. `defaults.authorization` declares one authorization config that every action inherits unless it overrides or opts out.**

Resolution in `YamlConfigAdapter::resolveAuthorization()`:

1. Action has no `authorization` key → use `defaults.authorization` verbatim (or no authorization, if there's no default).
2. Action has `authorization: false` (or the equivalent `authorization: ~`) → no authorization for this action, regardless of the default.
3. Action has `authorization:` with fields but no `type`, or the same `type` as the default → shallow array-merge, action fields win.
4. Action has `authorization:` with a different `type` → full replacement, no merge.

Rule 2 exists because `isset($config['authorization'])` is `false` for both "key absent" and "key present with a falsy/null value" — without a dedicated check, there would be no way to say "this action, unlike the rest, has no authorization" once a default is set. This matters for the token action itself: it must stay unauthenticated even when every other action in the file inherits a dynamic default pointing at it.

`false` is the recommended spelling for the opt-out, over `~`/`null`: both are detected the same way (via `array_key_exists`, never `isset`), but `authorization: false` reads as a deliberate decision ("no auth here") to someone scanning the file later, where `authorization: ~` can look like an accidentally-emptied field. `~` still works, unchanged, for anyone who already relies on it. Any other scalar (notably `true`) is rejected eagerly at YAML-load time: `YamlConfigAdapter`'s constructor validates every action's `authorization` value is a map, `false`, or `null` before any action is ever dispatched — the gap this closes was found by hand: an action config of `authorization: false` written *before* this rule existed reached `AuthorizationConfig::fromArray()` or a `[...$default, ...$own]` spread with a non-array, non-null value and failed with a generic PHP `\Error`/`\TypeError` at dispatch time, not a message naming the action.

Rule 4 exists because a naive unconditional merge would leave stale keys from a different `type` lying around — e.g. merging a `bearer` override onto a `dynamic` default would carry over `action`/`token_field`/`ttl` as spurious entries, which `StaticAuthorizationConfig::fromArray()` would then treat as extra `params`.

`defaults.authorization` is validated eagerly in the constructor via `AuthorizationConfig::fromArray()` (result discarded, only for validation) — consistent with [0001](0001-stateless-actions-and-declarative-yaml.md)'s "fail fast at configuration time": a malformed default fails when the YAML loads, not on the first action that happens to use it.

## Alternatives considered

1. **A named-presets map (`authorization_presets: { ... }`, actions reference by name)**
   - Pros: supports more than one shared config per file
   - Cons: solves a problem nobody reported — one token action per integration is the overwhelmingly common shape; adds a second concept (preset name) on top of the thing being referenced
   - Rejected: YAGNI; `defaults:` can grow a list-based mechanism later if a real multi-preset need shows up, without breaking this shape

2. **Inherit by placing shared fields directly on the integration's YAML root (no `defaults:` wrapper)**
   - Pros: one less level of nesting
   - Cons: ambiguous with action names — the top-level keys are already a flat `{name: config}` map of actions (`webhooks` is the one existing exception); a root-level `authorization:` key would be indistinguishable from an action named "authorization"
   - Rejected: a reserved `defaults:` key, parallel to the existing reserved `webhooks:` key, keeps the namespace unambiguous

3. **Full override only — no field-level merge (rule 3 would just replace wholesale)**
   - Pros: simpler resolution logic, no merge semantics to document
   - Cons: defeats half the purpose; the motivating case (`GetOrderDetails` needing a longer `ttl`, otherwise identical to the default) would still require repeating `action` and `token_field`
   - Rejected: field-level override is what makes `defaults:` worth having over just... not having a default

4. **Opt-out via a string sentinel** (e.g. `authorization: none`)
   - Pros: self-documenting without a comment
   - Cons: introduces a magic string with no type-system backing, and a new value space (strings) alongside the existing map/bool/null; `false` reads just as clearly ("no auth") while staying a plain boolean — a type both PHP and the YAML parser already treat as a normal scalar, with no new convention to spell correctly
   - Rejected in favor of `false`: same self-documenting benefit, no new string convention to learn or typo

5. **`~`/`null` only, no `false` alias** (the form this ADR originally shipped with)
   - Pros: one spelling for the opt-out, nothing else to document
   - Cons: `authorization: ~` is easy to misread as an accidentally-emptied field rather than a deliberate choice, especially months later in a file with several actions; this was raised directly during review before the first release of this feature
   - Rejected in favor of also accepting `false`: both are detected identically (`array_key_exists`, not `isset`) so supporting both costs one extra equality check, and `false` is the one recommended in docs going forward — `~` stays supported, not deprecated, since removing it would be a breaking change for no benefit

## Consequences

**Positive:**
- An integration with N protected actions behind one token action declares the dynamic block once instead of N times.
- Per-action divergence (different `ttl`, different type entirely, explicit opt-out) remains fully expressible — `defaults:` is additive, not a constraint.
- No change for any existing integration YAML: a file with no `defaults:` section behaves byte-for-byte as before.

**Negative:**
- One more reserved top-level key (`defaults`) in the YAML namespace, alongside `webhooks`.
- The merge/override/opt-out resolution (4 rules) is more to hold in mind than "each action is fully self-contained" — mitigated by keeping the logic in one small, directly-tested method (`resolveAuthorization()`) and documenting the rules in [authorization.md](../getting-started/authorization.md).

## References

- [`YamlConfigAdapter::resolveAuthorization()`](../../src/Infrastructure/Adapter/YamlConfigAdapter.php) — merge/override/opt-out logic
- [`tests/Infrastructure/YamlConfigAdapterTest.php`](../../tests/Infrastructure/YamlConfigAdapterTest.php) — inheritance, merge, type-mismatch replacement, both opt-out spellings (`false` and `~`), invalid-default and invalid-authorization-type fail-fast
- [`docs/getting-started/authorization.md`](../getting-started/authorization.md) — user-facing documentation
- [0001](0001-stateless-actions-and-declarative-yaml.md) — fail-fast-at-configuration-time principle this ADR follows for `defaults.authorization` validation
