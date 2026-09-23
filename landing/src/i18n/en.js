export default {
    roadmapColumns: [
        {
            "title": "Released",
            "items": [
                "v8.0.0: Scalar-only lifecycle events, YAML webhook definitions, form-encoded support, SSRF protection.",
                "Declarative retries & timeouts, optional PHPStan rules, request middleware for signing."
            ]
        },
        {
            "title": "Next",
            "items": [
                "v8.1.0: Enhanced observability with custom metric collection.",
                "Advanced retry strategies and circuit-breaker patterns.",
                "Multi-connection resolver improvements."
            ]
        },
        {
            "title": "Later",
            "items": [
                "Legacy migration guides for v7 → v8 adoption.",
                "Integration design workshop materials and case studies."
            ]
        }
    ],
    roadmapLink: "Read the full roadmap →",
    roadmapSub: "Latest: v8.0.0 with improved security, simplified configuration, and type safety. See UPGRADE-8.0.md for migration guide.",
    statusH2: "Roadmap",
    parallelComment: "// Concurrent with the built-in REST adapter; request middleware runs sequentially",
    demoSoon: "Live demo · coming soon",
    demoSource: "Demo source",
    navRoadmap: "Roadmap",
    metaDescription: "Connect external APIs to Symfony with declarative actions, typed responses and concurrent requests. Explore the code, architecture and examples.",
    lang: 'en',

    // Nav
    navProblem: 'Problem',
    navPattern:  'The Pattern',
    navWebhooks: 'Webhooks',

    // Hero
    heroH1:  'Stop writing integration code twice.',
    heroP: "One predictable structure for your external API integrations: declarative actions, response mappers and typed DTOs. Built for Symfony, distilled from production integrations in logistics and travel.",
    heroBenefits: [
        '&#10003;&nbsp;Token, Bearer &amp; API Key auth',
        '&#10003;&nbsp;Parallel requests',
        '&#10003;&nbsp;Typed DTOs',
        '&#10003;&nbsp;Inbound webhooks',
    ],
    heroBtn1: 'See the pattern',
    heroBtn2: 'GitHub',

    // Install box
    copyHint: 'Copied!',

    // Business value section
    bizEyebrow: 'Why it matters',
    bizH2:      'The cost of no standard compounds.',
    bizItems: [
        { stat: 'Less boilerplate', desc: 'Generate a consistent starting point for actions, mappers and typed responses.' },
        { stat: '1 command',          desc: 'Scaffold the action, mapper and response for any endpoint. Your whole team generates the same structure, every time.' },
        { stat: 'Zero rewrites',      desc: 'Installs alongside your existing code. New endpoints follow the standard; legacy integrations migrate at your pace.' },
    ],

    // Extension points section
    extEyebrow: 'Built to extend',
    extH2:      'Replace any part. Keep the rest.',
    extSub:     'Every infrastructure boundary is an interface. Swap the HTTP client, customise path resolution, or add batch support &mdash; without touching the engine.',
    extItems: [
        { iface: 'ClientInterface',               desc: 'Replace the HTTP client. Tag your implementation and the engine discovers it automatically via Symfony DI.' },
        { iface: 'PathResolvableContextInterface', desc: 'Complex path logic beyond {placeholders}. Return null to fall back to the default placeholder resolver.' },
        { iface: 'BatchClientInterface',           desc: 'Mark your client as batch-capable for concurrent dispatch. The built-in REST client already implements this.' },
        { iface: 'FakeClient &middot; FakeCache',  desc: 'Built-in test doubles. Test your mappers and actions in isolation &mdash; no mocks, no real HTTP required.' },
    ],

    // Problem section
    problemEyebrow: 'The Problem',
    problemH2:  'Integration debt accumulates by default.',
    problemSub: 'Each new API added without a standard costs your team days to set up and compounds with every new integration. Hardcoded URLs, duplicated token handling, arrays leaking into domain code &mdash; the next one is always harder than the last.',
    compareWithout: 'Without a standard',
    compareWith:    'With Integration Engine',
    compareItems: [
        { without: '700-line god classes',              with: 'One typed action per endpoint' },
        { without: 'Token handling duplicated everywhere', with: 'Auth declared once in YAML' },
        { without: 'Arrays leaking into domain',        with: 'Typed DTOs from every response' },
        { without: 'Sequential HTTP calls',             with: 'Parallel execution built in' },
    ],

    // Parallel section
    parallelEyebrow:      'Parallel Requests',
    parallelH2:           'Stop waiting for APIs one by one.',
    parallelSub: "The built-in REST adapter starts batch requests before reading their responses. Requests can overlap instead of waiting one by one. Actual duration depends on the provider, connection limits and middleware; request middleware uses sequential execution.",


    // Mid-page CTA
    midCtaText: 'Ready to add the pattern to your next project?',
    midCtaBtn:  'Read the documentation',
    midCtaAlt:  'See the full pattern',

    // Get started section
    startEyebrow:    'Get started',
    startH2:         'Three steps. First integration running.',
    startStep1Title: 'Install',
    startStep1Code:  'composer require carlosgude/integration-engine',
    startStep2Title: 'Scaffold',
    startStep2Code:  'php bin/console make:integration MyApi GetUser',
    startStep2Tree:  `src/Infrastructure/Integrations/MyApi/\n├─ MyApi.yaml                  <span class="cm">← the endpoint, declared</span>\n├─ MyApiIntegration.php\n└─ GetUser/\n   ├─ Request/GetUserAction.php   <span class="cm">← what goes in</span>\n   └─ Response/\n      ├─ GetUserMapper.php        <span class="cm">← payload → DTO</span>\n      └─ GetUserResponse.php      <span class="cm">← the typed DTO</span>`,
    startStep2Desc:  'Add the logic and 3 lines to MyApi.yaml &mdash; done.',
    startSub:        'Installs alongside your existing code. No big-bang rewrite &mdash; use the pattern for the next new endpoint and migrate legacy at your own pace.',
    startStep3Title: 'Go deeper',
    startStep3Desc:  'Dynamic auth, batch requests and custom contexts are all in the documentation.',
    startStep3Link:  'Read the docs →',
    startGenYaml:             '← add your endpoint entry here',
    startGenAction:           '← HTTP method, path, auth',
    startGenResponse:         '← typed DTO',
    startGenMapper:           '← raw array → DTO',
    startGenIncrementalLabel: '# New endpoint, same integration:',
    startGenIncrementalNote:  '# → Adds CreateOrder/ alongside GetUser/. Existing files are never overwritten.',
    startGenDocsLink:         'What goes inside the generated files? See the docs →',

    // Pattern section
    patternEyebrow:    'The Pattern',
    patternH2:         'Five antipatterns the engine solves',
    patternExpandLabel:   'Show all 5 antipatterns in detail',
    patternCollapseLabel: 'Hide details',
    patternSub: "Illustrative examples of the same integration concerns, with and without the pattern.",
    withoutPattern: 'Without pattern',
    enginePattern:  'Engine pattern',

    // Pattern 1
    p1Title:   'Integration configuration',
    p1Anti:    '&#10007; The base URL and paths live hardcoded in each method. There&rsquo;s no single place to see which endpoints exist.',
    p1Sol:     '&#10003; One YAML file per integration declares base_url, paths and auth. Complete contract at a glance.',
    p1CmBase:  '// Base URL lives here, not in any config file.',
    p1Insight: '<strong>Why it matters:</strong> with 20 endpoints, finding which one calls which URL requires reading every method of the God class. With YAML, a new developer opens one file and sees the complete contract. If you change <code>base_url</code> or add authentication, there is a single point of change.',

    // Pattern 2
    p2Title:       'Route building with parameters',
    p2Anti:        '&#10007; Concatenating strings to build URLs is prone to silent typos. A <code>null</code> produces a valid but semantically incorrect URL.',
    p2Sol:         '&#10003; <code>{placeholder}</code> templates in YAML resolved by <code>DefaultActionContext</code>. The engine throws an immediate exception if a parameter is missing.',
    p2CmOneParam:  '// One parameter in the path',
    p2CmTwoParam:  '// Two parameters in the path',
    p2CmNull:      '// If $stationId === null:\n// &rarr; /photoStationById/de/\n// &rarr; HTTP 404 with no descriptive exception.\n// The error surfaces late, far from the source.',
    p2CmMissing:   '// If &apos;stationId&apos; is missing: immediate, descriptive exception\n// before the HTTP call is made.',
    p2Insight:     '<strong>Why it matters:</strong> string concatenation fails silently. The engine&rsquo;s placeholders are contracts: if one is missing, the error is immediate and descriptive, not a mysterious 404 two layers below.',

    // Pattern 3
    p3Title:         'Response mapping',
    p3Anti:          '&#10007; Raw API fields (<code>\'title\'</code>, <code>\'photos\'</code>, <code>\'photoBaseUrl\'</code>) leak to all layers. If the API renames a field, the error appears in multiple files.',
    p3Sol:           '&#10003; One <code>Mapper</code> accesses the raw fields. The rest of the code talks to typed DTOs.',
    p3CmRawField:    '// raw API field',
    p3CmNotLng:      '// not &apos;lng&apos;, not &apos;longitude&apos;',
    p3CmPrivate:     '// private convention',
    p3CmRenameEvery: '// If the API renames &apos;title&apos; to &apos;name&apos;: search and fix EVERY\n// file that accesses the array. How many are there?',
    p3CmOnlyPlace:   '// only place',
    p3CmRenameOne:   '// If the API renames &apos;title&apos; to &apos;name&apos;: only this line changes.\n// No other file touches raw API fields.',
    p3Insight:       '<strong>Why it matters:</strong> without a mapper, knowledge of the API fields leaks into every class that processes the response. With the engine, <code>StationDto::fromApiData()</code> is the only point of contact. If the API renames a field, there is exactly one place to fix.',

    // Pattern 4
    p4Title:       'Anti-Corruption Layer',
    p4Anti:        '&#10007; The controller imports the HTTP client directly. Changing the API provider means touching every controller that consumes it.',
    p4Sol:         '&#10003; <code>StationService</code> is the only boundary between the domain and the integration. Controllers only see their own domain objects.',
    p4CmMapsConv:  '// maps raw _hasPhoto, _photoUrl conventions...',
    p4CmSwitchBad: '// Switch API &rarr; touch this controller,\n// and all others that do the same.',
    p4CmSwitchGood:'// Switch API &rarr; StationService absorbs the change.\n// This controller does not change.',
    p4Insight:     '<strong>Why it matters:</strong> without an ACL, the controller is coupled to <code>RailwayApiService</code> and its private conventions (<code>_hasPhoto</code>). With <code>StationService</code> as the only boundary, controllers only import domain objects and the cost of switching providers is reduced to a single file.',

    // Pattern 5
    p5Title:        'Request batching',
    p5Anti:         '&#10007; The sequential <code>foreach</code> blocks: each request waits for the previous one. Total time scales linearly.',
    p5Sol: "&#10003; <code>sendManyOrFail()</code> groups requests through a batch-capable client. The built-in REST adapter uses lazy HTTP responses for concurrency.",
    p5CmBlocked:    '// HTTP request &mdash; others wait here, blocked',
    p5CmBatchBad: "// Each request waits for the previous response.",
    p5CmAllSame: "// Concurrent when the adapter and middleware chain support batching",
    p5CmBatchGood: "// Inspect each outcome with sendMany(), or fail after the batch with sendManyOrFail().",
    p5Insight:      '<strong>Why it matters:</strong> individual failures never abort the batch &mdash; each key resolves independently. <code>sendMany()</code> returns a <code>BatchResultCollection</code> where you inspect each outcome; <code>sendManyOrFail()</code> throws on the first failure after the full batch has run. The default REST client already implements <code>BatchClientInterface</code> via lazy Symfony HttpClient responses &mdash; zero additional configuration.',

    // Social Proof section
    proofEyebrow: "Engineering evidence",
    proofH2: "Quality you can inspect.",
    proofItems: [
        {
            "stat": "Quality gates",
            "desc": "PHPStan at maximum level, unit tests and mutation testing. Inspect the results in the repository."
        },
        {
            "stat": "REST · GraphQL · Form",
            "desc": "Built-in adapters with per-integration wiring and support for your own clients."
        },
        {
            "stat": "Concurrent requests",
            "desc": "Batch-capable clients can overlap HTTP calls. Request middleware uses sequential execution."
        },
        {
            "stat": "Production experience",
            "desc": "Distilled from integrations in logistics and travel."
        }
    ],


    // Webhook showcase section
    webhookEyebrow: 'Inbound Webhooks',
    webhookH2:      'Receive from any platform.',
    webhookSub:     '<strong>v7.0:</strong> Symfony&rsquo;s Webhook component, wired. A parser verifies the signature and decodes the payload; your listener receives a typed event instead of a raw array. `make:webhook` writes the parser, the mapper, the DTO and the consumer into your app &mdash; the classes that know your provider belong to you, not to the bundle.',
    webhookPlatforms: "Hex HMAC &nbsp;•&nbsp; Base64 HMAC &nbsp;•&nbsp; Timestamped HMAC",
    webhookFeatures: [
        'Signature schemes covered: hex HMAC behind a prefix, raw HMAC in base64, timestamped HMAC',
        'Typed events in your listeners, never a raw payload array',
        'One parser class per event type &mdash; no controller to write',
        'Async when you want it: Symfony hands the event to Messenger',
        'Duplicate detection by payload fingerprint, over storage you provide',
    ],
    webhookBtn:     'View webhook guide →',

    // Observability section
    obsEyebrow: 'Observability',
    obsH2:      'Production monitoring built in.',
    obsSub: "Scalar lifecycle events expose bounded metadata for requests, mappings, failures, token refreshes and webhooks. Bundle-managed engines dispatch through Symfony's event dispatcher; applications own metrics storage and exporters.",
    obsFeatures: [
        'Scalar-only lifecycle metadata: no request, response, token or Throwable objects',
        'Logical operation duration, status and response/exception class metadata',
        'Symfony #[AsEventListener] support for bundle-managed engines',
        'LifecycleEventDispatcher for deliberate manual/shared-dispatcher wiring',
        'Optional ObservabilitySetup helper for logging, metrics callbacks and alerts',
    ],
    obsBtn:     'View observability guide →',

    // Wiki section
    wikiEyebrow:   'Learn & Reference',
    wikiH2:        'Complete documentation and guides.',
    wikiSub:       'Configuration, patterns, testing, troubleshooting. Architecture Decision Records explaining every design choice. Migration guides for every upgrade path.',
    wikiFeatures: [
        { title: 'Getting Started', desc: 'Quick start guide, installation, and basic usage', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/getting-started/README.md' },
        { title: 'Architecture', desc: 'Core abstractions, data flow, and design patterns', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/ARCHITECTURE.md' },
        { title: 'Testing', desc: 'Test structure, strategies, and running tests', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/TESTING.md' },
        { title: 'Lifecycle Events', desc: 'Scalar observability events and timing semantics', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/LIFECYCLE.md' },
        { title: 'Observability', desc: 'Symfony listeners, helper wiring, metrics and logging', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/OBSERVABILITY.md' },
        { title: 'Webhooks', desc: 'Authenticated inbound webhooks and application-owned idempotency', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/WEBHOOK.md' },
    ],
    wikiBtn:     'Browse documentation →',

    // Thanks section
    thanksEyebrow: 'Before you go',
    thanksH2:      'Thanks for reading.',
    thanksP: "This bundle is distilled from production integrations in logistics and travel. I made the pattern explicit, tested it and opened the code so other teams can use and improve it.",

    // CTA
    ctaEyebrow:   'Get in touch',
    ctaH2:        'Start building your next integration today.',
    ctaSub: "Send me a message, open a GitHub Discussion, or install the bundle and try it.",
    ctaEmailLabel: "Send me an email",
    ctaEmail:     'hi@integrationengine.dev',
    ctaEmailHref: 'mailto:hi@integrationengine.dev',
    ctaDiscuss:   'Join Discussions',
};
