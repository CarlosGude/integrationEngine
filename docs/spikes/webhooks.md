# Spike: Inbound Webhooks Architecture

**Objective:** Verify the contract of Symfony's webhook components across versions 6.4, 7.4, and 8.x to decide on compatibility and design constraints.

**Timeline:** Day 46, ~2 hours (timebox)

---

## Investigation Checklist

### 1. AbstractRequestParser Contract

**Question:** What is the expected signature and behavior of `AbstractRequestParser`?

- [ ] Location in each version:
  - [ ] Symfony 6.4: `symfony/webhook/src/RemoteEventParser/AbstractRequestParser.php`
  - [ ] Symfony 7.4: same path
  - [ ] Symfony 8.x: same path
  
- [ ] Method signature of `parse()` / `parseRequest()`:
  - [ ] Parameters: `Request`, return type
  - [ ] Null handling: can return `null`? Throws?
  - [ ] Exception types thrown
  
- [ ] Link to exact line(s):
  - **6.4:** 
  - **7.4:** 
  - **8.x:** 

### 2. RemoteEvent Interface

**Question:** What does `RemoteEvent` require, and is it an interface or class?

- [ ] Location and inheritance:
  - [ ] Base class or interface?
  - [ ] Methods/properties required
  - [ ] Serializable?
  
- [ ] Versioning changes (6.4 → 7.4 → 8.x):
  - [ ] Backward compatible?
  - [ ] Breaking changes documented?
  
- [ ] Link to exact line(s):
  - **6.4:** 
  - **7.4:** 
  - **8.x:** 

### 3. WebhookController Contract

**Question:** What are the expectations for the controller that consumes our parsed event?

- [ ] Expected signature (what does Symfony call, and when)?
  - [ ] Method name
  - [ ] Parameters (RemoteEvent, Request, etc.)
  - [ ] Expected return type (Response, null, int?)
  
- [ ] What happens if the handler returns `null` vs. a Response?
  - [ ] Default behavior (204? 200?)
  - [ ] Error handling (exception vs. failed Response)
  
- [ ] Does Symfony call `parseRequest()` for every event?
  - [ ] What if parser returns `null` (event not for us)?
  - [ ] Is that a silent skip or logged?
  
- [ ] Link to exact line(s) in WebhookController:
  - **6.4:** 
  - **7.4:** 
  - **8.x:** 

### 4. Rejection Scenarios

**Question:** How should we signal that a webhook is rejected (invalid signature, unknown type, etc.)?

- [ ] Options:
  - [ ] Throw exception → HTTP status?
  - [ ] Return special Response?
  - [ ] Call `reject()` method if it exists?
  
- [ ] HTTP status codes:
  - [ ] Valid signature, wrong type → 404? 202?
  - [ ] Invalid signature → 403? 401? 406?
  - [ ] Malformed JSON → 400?
  - [ ] Transient error (rate limit) → 429? 503?
  
- [ ] Does Symfony provide a standard rejection interface?
  - [ ] `RejectWebhook` exception or class?
  - [ ] Link:
  
### 5. Integration Flow Diagram

**Question:** Synthesize the complete flow.

```
HTTP POST /webhook/stripe
  ↓
framework.webhook.routing.stripe (Symfony)
  ↓
IntegrationWebhookRequestParser (ours)
  ├─ verify signature
  ├─ resolve event type
  ├─ call mapper
  ├─ if error → reject with reason
  └─ if OK → return RemoteEvent
  ↓
YourWebhookConsumer (Symfony calls this)
  ├─ receive RemoteEvent
  ├─ process (update DB, queue message, etc.)
  └─ return 202 Accepted or null
  ↓
HTTP 202 / 204 / 200
```

**Verify each step is correct per version:**
- [ ] **6.4:**
- [ ] **7.4:**
- [ ] **8.x:**

---

## Decision Points

After investigation, decide on:

1. **Minimum Symfony version** for webhook support
   - Constraint in `composer.json`
   
2. **AbstractRequestParser** implementation
   - Return type: `?RemoteEvent` vs throwing exception
   - When to throw, when to skip
   
3. **Rejection codes and exceptions**
   - Create `RejectWebhookException(code, message)` or use Symfony's?
   - Map rejection codes → HTTP status
   
4. **Idempotency guarantee**
   - Deduplication by webhook ID?
   - Where: in parser, in consumer, in repository?
   - Document in ADR-0010

---

## Evidence Trail

(Fill in exact file paths and line numbers as you investigate)

- **6.4 sources checked:**
  
- **7.4 sources checked:**
  
- **8.x sources checked:**

---

## Next Actions

- [ ] Write ADR-0009: Inbound Webhooks (design, rejection model, parser contract)
- [ ] Write ADR-0010: Webhook Idempotency (deduplication strategy, implementation location)
- [ ] If spike reveals unknowns: schedule follow-up; list blockers
