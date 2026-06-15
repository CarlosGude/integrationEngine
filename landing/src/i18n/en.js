export default {
    lang: 'en',

    // Nav
    navProblem: 'Problem',
    navPattern:  'The Pattern',
    navExample:  'Example',

    // Hero
    heroH1:  'Stop writing integration code twice.',
    heroP:   'Every integration your team ships follows the same predictable standard. New developers understand any existing API in minutes &mdash; not days. Automatic OAuth2, parallel requests and typed DTOs built in. One Symfony bundle &mdash; distilled from three years of production integrations.',
    heroBenefits: [
        '&#10003;&nbsp;OAuth2, Bearer &amp; API Key',
        '&#10003;&nbsp;Parallel requests',
        '&#10003;&nbsp;Typed DTOs',
        '&#10003;&nbsp;Symfony native',
    ],
    heroBtn1: 'See the pattern',
    heroBtn2: 'GitHub',

    // Install box
    copyHint: 'Copied!',

    // Business value section
    bizEyebrow: 'Why it matters',
    bizH2:      'The cost of no standard compounds.',
    bizItems: [
        { stat: 'Days &rarr; Hours',  desc: 'Time from zero to a working, tested integration &mdash; including OAuth, parallel calls and typed responses.' },
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
    problemSub: 'Each new API added without a standard costs your team days to set up and compounds with every new integration. Hardcoded URLs, duplicated OAuth logic, arrays leaking into domain code &mdash; the next one is always harder than the last.',
    compareWithout: 'Without a standard',
    compareWith:    'With Integration Engine',
    compareItems: [
        { without: '700-line god classes',              with: 'One typed action per endpoint' },
        { without: 'OAuth logic duplicated everywhere', with: 'Auth declared once in YAML' },
        { without: 'Arrays leaking into domain',        with: 'Typed DTOs from every response' },
        { without: 'Sequential HTTP calls',             with: 'Parallel execution built in' },
    ],

    // Parallel section
    parallelEyebrow:      'Parallel Requests',
    parallelH2:           'Stop waiting for APIs one by one.',
    parallelSub:          '<code>sendManyOrFail()</code> dispatches all requests concurrently. Total time &asymp; the slowest single request &mdash; regardless of how many you send. In production, a Booking.com availability search requires 4 parallel queries for a small city and 17 for Paris &mdash; per customer. This handles it.',
    parallelBefore:       'Sequential (foreach)',
    parallelAfter:        'Parallel (sendManyOrFail)',
    parallelBeforeTime:   '4.2s',
    parallelAfterTime:    '0.8s',
    parallelBeforeDetail: '10 requests &times; 420ms each',
    parallelAfterDetail:  '10 requests, runs concurrently',

    // Stripe example section
    stripeEyebrow: 'Real Example',
    stripeH2:      'A Stripe integration in under 30 lines.',
    stripeSub:     'One YAML entry. One mapper. One typed response. OAuth2 token refresh is handled automatically &mdash; no token logic in your application code.',
    stripeBtn:     'View source on GitHub',

    // Mid-page CTA
    midCtaText: 'Ready to add the pattern to your next project?',
    midCtaBtn:  'Read the documentation',
    midCtaAlt:  'See the full pattern',

    // Structure section
    structureEyebrow:   'The Solution',
    structureH2:        'One predictable structure for every integration',
    structureSub:       'If you know one integration, you know them all. The engine enforces the same shape across every API you integrate.',
    structureYamlHdr:   'ACTION MAP (YAML)',
    structureDirHdr:    'TYPICAL DIRECTORY',

    // Structure code comments
    cmContract:  '# RailwayStations.yaml &mdash; contract visible at a glance',
    cmFacade:    '&larr; facade',
    cmActionMap: '&larr; action map',

    // Structure auth panel
    structureAuthHdr:  'DYNAMIC AUTH (OAUTH2 &middot; BEARER &middot; API KEY)',
    cmAuthOnce:        '# Token fetched once, cached 60 min, retried automatically on 401',
    cmAuthField:       '&larr; field in the token response',
    cmAuthCached:      '&larr; cached per integration, shared across workers',

    // Get started section
    startEyebrow:    'Get started',
    startH2:         'Three steps. First integration running.',
    startStep1Title: 'Install',
    startStep1Code:  'composer require carlosgude/integration-engine',
    startStep2Title: 'Scaffold',
    startStep2Code:  'php bin/console make:integration MyApi GetUser',
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
    patternSub:           'The same endpoints, two implementations. Each section shows the real classes from the project.',
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
    p5Sol:          '&#10003; <code>sendManyOrFail()</code> dispatches all in parallel. Total time &asymp; the slowest request, regardless of the number of items.',
    p5CmBlocked:    '// HTTP request &mdash; others wait here, blocked',
    p5CmBatchBad:   '//  3 stations &times; 250ms = ~750ms\n// 10 stations &times; 250ms = ~2500ms  &larr; scales linearly',
    p5CmAllSame:    '// all go out at the same time &mdash; total time &asymp; the slowest',
    p5CmBatchGood:  '//  3 stations &rarr; ~250ms   (the slowest, not the sum)\n// 10 stations &rarr; ~250ms   (does not scale)',
    p5Insight:      '<strong>Why it matters:</strong> individual failures never abort the batch &mdash; each key resolves independently. <code>sendMany()</code> returns a <code>BatchResultCollection</code> where you inspect each outcome; <code>sendManyOrFail()</code> throws on the first failure after the full batch has run. The default REST client already implements <code>BatchClientInterface</code> via lazy Symfony HttpClient responses &mdash; zero additional configuration.',

    // Summary
    summaryEyebrow:    'Summary',
    summaryH2:         'Without pattern vs Engine pattern',
    summaryThConcept:  'Concept',
    summaryThWithout:  'Without pattern',
    summaryThEngine:   'Engine pattern',
    summaryRows: [
        { concept: 'Endpoint declaration',        without: '&#10007; Scattered across God class methods',                engine: '&#10003; One YAML file per integration' },
        { concept: 'URL building',                without: '&#10007; String concatenation, fails silently',             engine: '&#10003; <code>{placeholders}</code> validated at runtime' },
        { concept: 'API fields in code',          without: '&#10007; Leaked to all layers',                             engine: '&#10003; Encapsulated in <code>Mapper</code> + <code>DTO</code>' },
        { concept: 'Return type',                 without: '&#10007; <code>array&lt;string,&nbsp;mixed&gt;</code> + <code>_</code> conventions', engine: '&#10003; Typed <code>ResponseInterface</code>' },
        { concept: 'Anti-Corruption Layer',       without: '&#10007; None &mdash; controller coupled to HTTP client',   engine: '&#10003; <code>StationService</code> as the only boundary' },
        { concept: 'Auth (Bearer, Basic, OAuth2)',without: 'Manual headers in each <code>request()</code>',             engine: '&#10003; Declared in YAML, managed by the engine' },
        { concept: 'Adding a new endpoint',       without: 'Method + URL + parsing + mapping scattered',                engine: '&#10003; Action + Mapper + Response + 3 YAML lines' },
        { concept: 'Batch',                       without: '&#10007; Sequential <code>foreach</code>, linear time',     engine: '&#10003; <code>sendManyOrFail()</code>, constant time' },
    ],

    // Thanks section
    thanksEyebrow: 'Before you go',
    thanksH2:      'Thanks for reading.',
    thanksP:       'I&rsquo;ve been using this pattern in production for three years &mdash; integrating Booking.com, Iberia, Lleego, Hostalia and more. Booking.com availability alone requires 4 parallel queries for a small city, 17 for Paris, per customer. An earlier version of this engine handled that without breaking a sweat. This bundle is what those three years taught me: made explicit, tested, and open.',

    // CTA
    ctaEyebrow:   'Get in touch',
    ctaH2:        'Start building your next integration today.',
    ctaSub:       'Drop us a line, open a GitHub Discussion, or install it and give it a try.',
    ctaEmailLabel:'Send us an email',
    ctaEmail:     'hi@integration.dev',
    ctaEmailHref: 'mailto:hi@integration.dev',
    ctaDiscuss:   'Join Discussions',
};
