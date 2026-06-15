export default {
    lang: 'en',

    // Nav
    navProblem: 'Problem',
    navPattern:  'The Pattern',

    // Hero
    heroH1:  'Your external integrations<br>deserve a pattern',
    heroP:   'Without structure, every API ends up with its own shape, its own logic, its own technical debt. After a few months you don&rsquo;t have integrations. You have a zoo.',
    heroBtn1: 'Explore the pattern',
    heroBtn2: 'Source code',

    // Install box
    copyHint: 'Copied!',

    // Problem section
    problemEyebrow: 'The Problem',
    problemH2:  'Why integrations degenerate',
    problemSub: 'Without a pattern, entropy wins. Every integration brings its own conventions and the code becomes archaeology.',
    problems: [
        { title: 'Inevitable god class',  desc: 'Without a defined entry point, HTTP methods, parsing and logic pile up in a single class. Impossible to test, impossible to scale.' },
        { title: 'Implicit contracts',    desc: 'Responses travel as <code>array&lt;string, mixed&gt;</code>. Every layer that touches them has to know the exact field names of the API.' },
        { title: 'Sequential batch',      desc: 'The <code>foreach</code> blocks: each request waits for the previous one. 10 items = 10&times; the time of one. It doesn&rsquo;t scale, and nothing warns you.' },
    ],

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

    // Pattern section
    patternEyebrow: 'The Pattern',
    patternH2:      'Five antipatterns the engine solves',
    patternSub:     'The same endpoints, two implementations. Each section shows the real classes from the project.',
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
    p3Title:        'Response mapping',
    p3Anti:         '&#10007; Raw API fields (<code>\'title\'</code>, <code>\'photos\'</code>, <code>\'photoBaseUrl\'</code>) leak to all layers. If the API renames a field, the error appears in multiple files.',
    p3Sol:          '&#10003; One <code>Mapper</code> accesses the raw fields. The rest of the code talks to typed DTOs.',
    p3CmRawField:   '// raw API field',
    p3CmNotLng:     '// not &apos;lng&apos;, not &apos;longitude&apos;',
    p3CmPrivate:    '// private convention',
    p3CmRenameEvery:'// If the API renames &apos;title&apos; to &apos;name&apos;: search and fix EVERY\n// file that accesses the array. How many are there?',
    p3CmOnlyPlace:  '// only place',
    p3CmRenameOne:  '// If the API renames &apos;title&apos; to &apos;name&apos;: only this line changes.\n// No other file touches raw API fields.',
    p3Insight:      '<strong>Why it matters:</strong> without a mapper, knowledge of the API fields leaks into every class that processes the response. With the engine, <code>StationDto::fromApiData()</code> is the only point of contact. If the API renames a field, there is exactly one place to fix.',

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
    p5Insight:      '<strong>Why it matters:</strong> <code>sendManyOrFail()</code> dispatches in parallel. The default REST client already implements <code>BatchClientInterface</code> &mdash; zero additional configuration. If one request fails, the exception identifies exactly which one, and the rest of the batch has already executed.',

    // CTA
    ctaEyebrow:   'Get in touch',
    ctaH2:        'Questions? Ideas? Feedback?',
    ctaSub:       'Drop us a line or join the conversation on GitHub Discussions.',
    ctaEmailLabel:'Send us an email',
    ctaEmail:     'hi@integration.dev',
    ctaEmailHref: 'mailto:hi@integration.dev',
    ctaDiscuss:   'Join Discussions',

    // Summary
    summaryEyebrow: 'Summary',
    summaryH2:      'Without pattern vs Engine pattern',
    summaryThConcept: 'Concept',
    summaryThWithout: 'Without pattern',
    summaryThEngine:  'Engine pattern',
    summaryRows: [
        { concept: 'Endpoint declaration',   without: '&#10007; Scattered across God class methods',                engine: '&#10003; One YAML file per integration' },
        { concept: 'URL building',           without: '&#10007; String concatenation, fails silently',             engine: '&#10003; <code>{placeholders}</code> validated at runtime' },
        { concept: 'API fields in code',     without: '&#10007; Leaked to all layers',                             engine: '&#10003; Encapsulated in <code>Mapper</code> + <code>DTO</code>' },
        { concept: 'Return type',            without: '&#10007; <code>array&lt;string,&nbsp;mixed&gt;</code> + <code>_</code> conventions', engine: '&#10003; Typed <code>ResponseInterface</code>' },
        { concept: 'Anti-Corruption Layer',  without: '&#10007; None &mdash; controller coupled to HTTP client',   engine: '&#10003; <code>StationService</code> as the only boundary' },
        { concept: 'Auth (Bearer, Basic, OAuth2)', without: 'Manual headers in each <code>request()</code>',       engine: '&#10003; Declared in YAML, managed by the engine' },
        { concept: 'Adding a new endpoint',  without: 'Method + URL + parsing + mapping scattered',                engine: '&#10003; Action + Mapper + Response + 3 YAML lines' },
        { concept: 'Batch',                  without: '&#10007; Sequential <code>foreach</code>, linear time',     engine: '&#10003; <code>sendManyOrFail()</code>, constant time' },
    ],
};
