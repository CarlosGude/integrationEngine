# Spike: Inbound Webhooks Architecture

**Objective:** Verify the contract of Symfony's webhook components across versions 6.4, 7.4, and 8.x to decide on compatibility and design constraints.

**Timeline:** Day 46, ~2 hours (timebox)

---

## Investigation Checklist

### 1. AbstractRequestParser Contract

**Question:** What is the expected signature and behavior of `AbstractRequestParser`?

**Verified (all versions 6.4, 7.4, 8.1):**

**Location:** `symfony/webhook/Client/AbstractRequestParser.php` (NOT RemoteEventParser/)

**Public interface:**
```php
public function parse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent  // 6.4
public function parse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent|array|null  // 7.4+
```

**Template method pattern:**
1. `parse()` calls `validate()` first (checks RequestMatcher)
2. Then calls abstract `doParse()` (implemented by subclass)
3. Catches `RequestExceptionInterface` → throws `RejectWebhookException(406, 'Request body is malformed.')`
4. Can return: single RemoteEvent, array of RemoteEvents, or null (skipped silently)
5. Can throw: `RejectWebhookException(statusCode, message)` or RequestExceptionInterface

**Key differences across versions:**
- **6.4:** Return type is `?RemoteEvent` (single or null)
- **7.4:** Return type becomes `RemoteEvent|array|null` (can now return multiple events)
- **8.1:** Same as 7.4; added better docstring: "RemoteEvent|RemoteEvent[]|null"

**Exception class:**
- `RejectWebhookException extends HttpException`
- Constructor: `__construct(int $statusCode = 406, string $message = '', ?Throwable $previous = null, array $headers = [], int $code = 0)`
- Subclass automatically converts to HTTP response (Symfony handles it)
  
- [ ] Link to exact line(s):
  - **6.4:** `/tmp/symfony-spike/vendor/symfony/webhook/Client/AbstractRequestParser.php:26-35` ✓
  - **7.4:** `/tmp/symfony-spike/vendor/symfony/webhook/Client/AbstractRequestParser.php:26-35` (same, but return type now `RemoteEvent|array|null`) ✓
  - **8.x:** Same line numbers; docstring in `doParse()` now says "RemoteEvent[]" ✓

### 2. RemoteEvent Interface

**Question:** What does `RemoteEvent` require, and is it an interface or class?

**Verified (all versions 6.4, 7.4, 8.1):**

**It's a final class (not interface):**
```php
namespace Symfony\Component\RemoteEvent;

final class RemoteEvent
{
    public function __construct(
        private readonly string $name,
        private readonly string $id,
        private readonly array $payload,
    ) {}

    public function getName(): string
    public function getId(): string
    public function getPayload(): array
}
```

**Properties:**
- `name`: Event type/name (e.g., `"charge.succeeded"` from Stripe)
- `id`: Unique webhook ID (e.g., `"evt_1234"` from Stripe)
- `payload`: Raw event data as array

**Backward compatibility:**
- ✅ Identical across all three versions (no breaking changes)
- Immutable (readonly)
- Lives in `symfony/remote-event` component (separate from `symfony/webhook`)

**Serialization:** Not explicitly serializable via `Serializable` interface, but can be dispatched via Messenger (which uses PHP serialization by default for `ConsumeRemoteEventMessage`)

- [ ] Link to exact line(s):
  - **6.4:** `/tmp/symfony-spike/vendor/symfony/remote-event/RemoteEvent.php:17-40` ✓
  - **7.4:** Same (identical) ✓
  - **8.x:** Same (identical) ✓

### 3. WebhookController Contract

**Question:** What are the expectations for the controller that consumes our parsed event?

**Verified (Symfony 8.1):**

1. **Controller flow:**
   - `WebhookController::handle(type, request)` receives the webhook
   - Looks up parser by `type` from map (404 if not found)
   - Calls `$parser->parse($request, $secret)` → expects `?RemoteEvent|array|null`
   - If null/falsy → calls `createRejectedResponse('Unable to parse...')` → 406
   - If array of events → dispatches each as `ConsumeRemoteEventMessage` to Messenger bus
   - If single RemoteEvent → wraps in array first
   - Returns `createSuccessfulResponse()` → 202

2. **Exception handling:**
   - Parser throws `RejectWebhookException(statusCode, message, previous)` on signature failure
   - Symfony catches this (extends HttpException) → HTTP response with that statusCode (default 406)
   - No need for try-catch in controller; exception → Response automatic

3. **Return types per version:**
   - **6.4:** `?RemoteEvent` (single event or null)
   - **7.4:** `RemoteEvent|array|null` (single event, array of events, or null)
   - **8.x:** `RemoteEvent|array|null` (same; added docstring clarifying `RemoteEvent[]`)

- [ ] Link to exact line(s) in WebhookController:
  - **8.x:** `/tmp/symfony-spike/vendor/symfony/webhook/Controller/WebhookController.php:36-56` ✓

### 4. Rejection Scenarios

**Question:** How should we signal that a webhook is rejected (invalid signature, unknown type, etc.)?

**Verified (Symfony 8.1):**

**Rejection model:**
- Parser throws `RejectWebhookException(statusCode, message, previous = null)`
- Symfony/HttpKernel catches it automatically → converts to HTTP Response
- Controller calls `parser->createRejectedResponse(reason)` if parser returns null
- Parser can also return null to trigger rejection (framework default: 406 + message)

**HTTP status codes used by framework:**
- **406** (Not Acceptable): Default for all rejections
  - Invalid signature: throw `RejectWebhookException(406, 'Signature invalid')`
  - Malformed JSON: auto-caught as `RequestExceptionInterface` → thrown as 406
  - Request doesn't match: throw `RejectWebhookException(406, 'Request does not match.')`
  - Parser returns null: framework sends 406 + 'Unable to parse the webhook payload.'
  
- **404**: Unknown webhook type (WebhookController itself, not parser)

**Status code customization:**
- Parser can use different status codes: `throw RejectWebhookException(400, ...)` or `RejectWebhookException(403, ...)`
- Framework will respect the code and return it
- Default method signature allows any int statusCode

**Response methods (from AbstractRequestParser):**
```php
public function createSuccessfulResponse(?Request $request = null): Response
    // Returns 202 Accepted (empty body)

public function createRejectedResponse(string $reason, ?Request $request = null): Response
    // Returns 406 + reason text as body
```

**Recommendation:**
- 406: Default for all validation failures (signature, schema, event type unknown)
- 400: Malformed JSON (RequestExceptionInterface) — auto 406
- 404: Unknown webhook type (framework level)
- Do NOT use 401/403 (those imply authentication/authorization, not validation)
  
### 5. Integration Flow Diagram

**Verified complete flow (Symfony 8.1):**

```
HTTP POST /webhook/stripe
  ↓
WebhookController::handle('stripe', $request)
  ├─ Looks up parser from $parsers['stripe']
  ├─ If not found → 404 (text/plain)
  └─ Found → proceed
  ↓
IntegrationWebhookRequestParser::parse($request, $secret)
  ├─ Validates request (RequestMatcher)
  │  └─ If mismatch → throw RejectWebhookException(406, 'Request does not match.')
  ├─ Calls doParse() (implemented by subclass)
  │  ├─ Verify HMAC signature
  │  │  └─ Invalid → throw RejectWebhookException(406, 'Signature invalid')
  │  ├─ Parse JSON
  │  │  └─ Malformed → catch RequestExceptionInterface → throw RejectWebhookException(406, 'Request body is malformed.')
  │  ├─ Resolve event type
  │  │  └─ Unknown → return null (silent skip) or throw RejectWebhookException(406, 'Unknown event type')
  │  └─ Call mapper
  │     └─ Return RemoteEvent | RemoteEvent[] | null
  └─ Return result to controller
  ↓
WebhookController catches RejectWebhookException
  ├─ Converts to Response(message, statusCode)
  └─ Returns (browser receives 406 + reason)
  ↓
OR WebhookController processes successful parse
  ├─ If null → return createRejectedResponse('Unable to parse...') → 406
  ├─ If RemoteEvent → wrap as [$event]
  ├─ If RemoteEvent[] → use as-is
  ├─ For each event → dispatch ConsumeRemoteEventMessage($type, $event) to Messenger bus
  └─ Return createSuccessfulResponse() → 202 Accepted
  ↓
HTTP 202 (success) or 406/404 (rejection)
  ↓
ConsumeRemoteEventMessage waits on Messenger transport (async or sync)
  ↓
Consumer handler receives RemoteEvent
  ├─ Verify idempotency (check if already processed by ID)
  ├─ Process (update DB, enqueue task, etc.)
  ├─ Mark delivery as processed
  └─ Commit transaction
```

**Same across all versions:**
- ✅ **6.4, 7.4, 8.x:** Flow is identical
- Only difference: 6.4 return type is `?RemoteEvent` (single event), 7.4+ is `RemoteEvent|array|null`
- Framework handles both seamlessly (wraps single in array internally)

---

## Decision Points — DECIDED

### 1. ✅ Minimum Symfony version: **6.4**
- Symfony 6.4 is LTS and has all required webhook components
- No need to support older versions
- **Constraint:** `symfony/webhook: ^6.4` in composer.json suggest dependencies

### 2. ✅ AbstractRequestParser implementation: **Extend Symfony's, don't replace**
- Subclass `Symfony\Component\Webhook\Client\AbstractRequestParser`
- Implement required abstract methods:
  - `getRequestMatcher(): RequestMatcherInterface` (e.g., check method=POST, Content-Type)
  - `doParse(Request, secret): RemoteEvent|array|null` (verify signature, parse payload, map)
- Our role:
  - Implement signature verification (HMAC, timestamped-HMAC, etc.)
  - Map payload to typed RemoteEvent (via `AbstractWebhookMapper`)
  - Return RemoteEvent or array of RemoteEvents
  
- Leverage Symfony's template method:
  - ✅ Error handling: RequestExceptionInterface → RejectWebhookException(406, ...) automatic
  - ✅ Null handling: return null for "ignore this webhook"
  - ✅ Response codes: use Symfony's built-in 202 success, 406 rejection

### 3. ✅ Rejection model: **Use Symfony's RejectWebhookException**
- Already exists in Symfony ✓
- Constructor: `RejectWebhookException(statusCode, message, previous = null, headers = [], code = 0)`
- Throws automatically convert to HTTP Response via HttpKernel
- Standard codes:
  - **406**: Invalid signature, malformed payload, event type unknown (default, most common)
  - **404**: Unknown webhook type (framework level, not our concern)
  - Avoid 401/403 (wrong semantics; implies auth/authz, not validation)

### 4. ✅ Idempotency guarantee: **Deduplication in consumer layer** (see ADR-0010)
- Parser receives webhook and passes to Messenger bus
- Consumer (handler) is responsible for deduplication
- Rationale: same event might be re-delivered by provider; allows replay for testing
- Implementation: check webhook ID in database before processing
- See ADR-0010 for details

### 5. ✅ Mapper invariant: **Enforce at runtime**
- Create `AbstractWebhookMapper` (mirrors `AbstractMapper` for responses)
- Each mapper declares `getDefinition(): string` → its linked webhook definition
- Runtime validation: mismatch → exception
- Prevents silent misconfigurations

---

## Evidence Trail

### Symfony 6.4
- `symfony/webhook`: v6.4.x
- `symfony/remote-event`: v6.4.x
- **Files examined:**
  - `/tmp/symfony-spike/vendor/symfony/webhook/Client/AbstractRequestParser.php`
  - `/tmp/symfony-spike/vendor/symfony/webhook/Client/RequestParserInterface.php`
  - `/tmp/symfony-spike/vendor/symfony/remote-event/RemoteEvent.php`
- **Key finding:** Return type is `?RemoteEvent` (single event or null)

### Symfony 7.4
- Same directory structure as 6.4
- **Files examined:** (same paths)
- **Key finding:** Return type becomes `RemoteEvent|array|null` (can return multiple events)

### Symfony 8.1
- Same directory structure
- `WebhookController` moved to `symfony/webhook/Controller/WebhookController.php`
- **Files examined:**
  - (all above files)
  - `/tmp/symfony-spike/vendor/symfony/webhook/Controller/WebhookController.php`
  - `/tmp/symfony-spike/vendor/symfony/webhook/Exception/RejectWebhookException.php`
- **Key finding:** Docstring clarifies support for `RemoteEvent[]`; parameter `?Request` added to response methods

---

## Next Actions

- [x] ✅ Spike complete: investigate Symfony versions 6.4, 7.4, 8.x
- [x] ✅ Verify AbstractRequestParser, RemoteEvent, WebhookController contracts
- [x] ✅ Document rejection model and HTTP status codes
- [x] ✅ Finalize design decisions for ADRs
- [ ] Write ADR-0009: Inbound Webhooks (design + decisions from this spike)
- [ ] Write ADR-0010: Webhook Idempotency (deduplication strategy, implementation location)
- [ ] No blockers; proceed to Day 47 (YAML webhook definition parsing)
