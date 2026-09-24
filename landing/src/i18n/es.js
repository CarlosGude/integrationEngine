export default {
    roadmapColumns: [
    {
        "title": "Publicada",
        "items": [
            "v9.0.0: Los parámetros de URL salen exclusivamente de context. Se conservan todos los campos del body.",
            "Respuestas tipadas, peticiones concurrentes, webhooks en YAML, reintentos y timeouts declarativos."
        ]
    },
    {
        "title": "Próximas propuestas",
        "items": [
            "Usar un transporte HTTP de Symfony proporcionado por la aplicación con los adaptadores incluidos.",
            "Un exportador de métricas propio, una vez definido su modelo de almacenamiento y workers."
        ]
    },
    {
        "title": "Criterio de planificación",
        "items": [
            "Las propuestas no tienen una versión ni una fecha de entrega comprometidas.",
            "Las funcionalidades se documentan como disponibles tras implementarlas y validarlas."
        ]
    }
],
    roadmapLink: "Ver la hoja de ruta completa →",
    roadmapSub: "Última versión: v9.0.0. Al actualizar, pasa los parámetros de URL del body al context. Mantenlos en body solo si la API también los exige en el payload.",
    releaseLink: "Notas de v9.0.0 →",
    migrationLink: "Ejemplos de contexto y migración →",
    statusH2: "Hoja de ruta",
    parallelComment: "// Concurrente con el adaptador REST incluido; con middleware de petición es secuencial",
    demoSoon: "Demo online · próximamente",
    demoSource: "Código de la demo",
    navRoadmap: "Hoja de ruta",
    metaDescription: "Conecta APIs externas con Symfony mediante acciones declarativas, respuestas tipadas y peticiones concurrentes. Explora el código, la arquitectura y los ejemplos.",
    lang: 'es',

    // Nav
    navProblem: 'El Problema',
    navPattern:  'El Patrón',
    navWebhooks: 'Webhooks',

    // Hero
    heroH1:  'Deja de escribir código de integración dos veces.',
    heroP: "Una estructura predecible para tus integraciones con APIs externas: acciones declarativas, mappers de respuestas y DTOs tipados. Un bundle de Symfony destilado de integraciones en producción en logística y viajes.",
    heroBenefits: [
        '&#10003;&nbsp;Auth por token, Bearer y API Key',
        '&#10003;&nbsp;Peticiones en paralelo',
        '&#10003;&nbsp;DTOs tipados',
        '&#10003;&nbsp;Webhooks inbound',
    ],
    heroBtn1: 'Ver el patrón',
    heroBtn2: 'GitHub',

    // Install box
    copyHint: '¡Copiado!',

    // Business value section
    bizEyebrow: 'Por qué importa',
    bizH2:      'El coste de no tener un estándar se acumula.',
    bizItems: [
        { stat: 'Menos código repetitivo', desc: 'Genera una estructura consistente para acciones, mappers y respuestas tipadas.' },
        { stat: '1 comando',          desc: 'Genera la action, el mapper y la respuesta para cualquier endpoint. Todo el equipo genera la misma estructura, siempre.' },
        { stat: 'Sin reescrituras',   desc: 'Se instala junto al código existente. Los nuevos endpoints siguen el estándar; las integraciones legacy migran a tu ritmo.' },
    ],

    // Extension points section
    extEyebrow: 'Diseñado para extender',
    extH2:      'Reemplaza cualquier parte. Conserva el resto.',
    extSub:     'Cada frontera de infraestructura es una interfaz. Cambia el cliente HTTP, personaliza la resolución de rutas o añade soporte batch &mdash; sin tocar el engine.',
    extItems: [
        { iface: 'ClientInterface',               desc: 'Reemplaza el cliente HTTP. Etiqueta tu implementación y el engine la descubre automáticamente vía Symfony DI.' },
        { iface: 'PathResolvableContextInterface', desc: 'Lógica de rutas más compleja que los {placeholders}. Devuelve null para caer al resolver por defecto.' },
        { iface: 'BatchClientInterface',           desc: 'Marca tu cliente como batch-capable para despacho concurrente. El cliente REST incluido ya lo implementa.' },
        { iface: 'FakeClient &middot; FakeCache',  desc: 'Test doubles incluidos. Testea mappers y actions en aislamiento &mdash; sin mocks, sin HTTP real.' },
    ],

    // Problem section
    problemEyebrow: 'El Problema',
    problemH2:  'La deuda de integración se acumula por defecto.',
    problemSub: 'Cada API añadida sin un estándar le cuesta a tu equipo días de configuración y se acumula con cada nueva integración. URLs hardcodeadas, gestión de tokens duplicada, arrays filtrándose al dominio &mdash; la siguiente siempre es más difícil que la anterior.',
    compareWithout: 'Sin un estándar',
    compareWith:    'Con Integration Engine',
    compareItems: [
        { without: 'God classes de 700 líneas',          with: 'Una acción tipada por endpoint' },
        { without: 'Gestión de tokens duplicada en todos lados', with: 'Auth declarada una vez en YAML' },
        { without: 'Arrays filtrándose al dominio',      with: 'DTOs tipados en cada respuesta' },
        { without: 'Llamadas HTTP secuenciales',         with: 'Ejecución en paralelo incluida' },
    ],

    // Parallel section
    parallelEyebrow:      'Peticiones en paralelo',
    parallelH2:           'Deja de esperar a las APIs una a una.',
    parallelSub: "El adaptador REST incluido inicia las peticiones del lote antes de leer sus respuestas. Las peticiones pueden solaparse en vez de esperar una a una. La duración depende del proveedor, los límites de conexión y los middlewares; con middleware de petición la ejecución es secuencial.",


    // Mid-page CTA
    midCtaText: '¿Listo para añadir el patrón a tu próximo proyecto?',
    midCtaBtn:  'Leer la documentación',
    midCtaAlt:  'Ver el patrón completo',

    // Get started section
    startEyebrow:    'Empieza',
    startH2:         'Tres pasos. Primera integración funcionando.',
    startStep1Title: 'Instala',
    startStep1Code:  'composer require carlosgude/integration-engine',
    startStep2Title: 'Genera',
    startStep2Code:  'php bin/console make:integration MyApi GetUser',
    startStep2Tree:  `src/Infrastructure/Integrations/MyApi/\n├─ MyApi.yaml                  <span class="cm">← el endpoint, declarado</span>\n├─ MyApiIntegration.php\n└─ GetUser/\n   ├─ Request/GetUserAction.php   <span class="cm">← lo que entra</span>\n   └─ Response/\n      ├─ GetUserMapper.php        <span class="cm">← payload → DTO</span>\n      └─ GetUserResponse.php      <span class="cm">← el DTO tipado</span>`,
    startStep2Desc:  'Añade la lógica y 3 líneas al MyApi.yaml &mdash; listo.',
    startSub:        'Se instala junto al código existente. Sin reescritura masiva &mdash; usa el patrón en el próximo endpoint nuevo y migra el código legacy a tu ritmo.',
    startStep3Title: 'Profundiza',
    startStep3Desc:  'Auth dinámica, batch requests y contextos personalizados están en la documentación.',
    startStep3Link:  'Leer la documentación →',
    startGenYaml:             '← añade aquí el entry del endpoint',
    startGenAction:           '← método HTTP, path, auth',
    startGenResponse:         '← DTO tipado',
    startGenMapper:           '← array crudo → DTO',
    startGenIncrementalLabel: '# Nuevo endpoint, misma integración:',
    startGenIncrementalNote:  '# → Añade CreateOrder/ junto a GetUser/. Los ficheros existentes nunca se sobreescriben.',
    startGenDocsLink:         '¿Qué va dentro de los ficheros generados? Ver la documentación →',

    // Pattern section
    patternEyebrow:    'El Patrón',
    patternH2:         'Cinco antipatrones que el engine resuelve',
    patternExpandLabel:   'Ver los 5 antipatrones en detalle',
    patternCollapseLabel: 'Ocultar detalle',
    patternSub: "Ejemplos ilustrativos de los mismos problemas de integración, con y sin el patrón.",
    withoutPattern: 'Sin patrón',
    enginePattern:  'Engine pattern',

    // Pattern 1
    p1Title:   'Configuración de la integración',
    p1Anti:    '&#10007; La URL base y los paths viven hardcodeados en cada método. No hay un sitio donde ver qué endpoints existen.',
    p1Sol:     '&#10003; Un fichero YAML por integración declara base_url, paths y auth. Contrato completo en un vistazo.',
    p1CmBase:  '// La URL base vive aquí, no en ningún fichero de config.',
    p1Insight: '<strong>Por qué importa:</strong> con 20 endpoints, encontrar cuál llama a qué URL requiere leer cada método de la God class. Con el YAML, un desarrollador nuevo abre un fichero y ve el contrato completo. Si cambias la <code>base_url</code> o añades autenticación, hay un único punto de cambio.',

    // Pattern 2
    p2Title:       'Construcción de rutas con parámetros',
    p2Anti:        '&#10007; Concatenar strings para construir la URL es propenso a typos silenciosos. Un <code>null</code> produce una URL válida pero semánticamente incorrecta.',
    p2Sol: "&#10003; Los valores de <code>{placeholder}</code> del YAML salen exclusivamente de <code>context</code>. Usa <code>DefaultActionContext</code>; si falta un parámetro, falla antes de HTTP.",
    p2CmOneParam:  '// Un parámetro en la ruta',
    p2CmTwoParam:  '// Dos parámetros en la ruta',
    p2CmNull:      '// Si $stationId === null:\n// &rarr; /photoStationById/de/\n// &rarr; HTTP 404 sin excepción descriptiva.\n// El error aparece tarde, lejos del origen.',
    p2CmMissing:   '// Si falta &apos;stationId&apos;: excepción inmediata y descriptiva\n// antes de que se haga la llamada HTTP.',
    p2Insight: "<strong>URL y payload explícitos:</strong> pasa los parámetros de URL en <code>context</code> y los campos del payload en <code>body</code>. Aunque ambos contengan <code>id</code>, el campo del body se conserva. El body nunca resuelve un parámetro de URL que falte.",

    // Pattern 3
    p3Title:         'Mapeo de la respuesta',
    p3Anti:          '&#10007; Los campos crudos de la API (<code>\'title\'</code>, <code>\'photos\'</code>, <code>\'photoBaseUrl\'</code>) se filtran a todas las capas. Si la API cambia un nombre de campo, el error aparece en múltiples ficheros.',
    p3Sol:           '&#10003; Un único <code>Mapper</code> accede a los campos crudos. El resto del código habla con DTOs tipados.',
    p3CmRawField:    '// campo crudo de la API',
    p3CmNotLng:      '// no &apos;lng&apos;, no &apos;longitude&apos;',
    p3CmPrivate:     '// convención privada',
    p3CmRenameEvery: '// Si la API cambia &apos;title&apos; por &apos;name&apos;: hay que buscar y corregir\n// en TODOS los ficheros que acceden al array. ¿Cuántos son?',
    p3CmOnlyPlace:   '// único sitio',
    p3CmRenameOne:   '// Si la API cambia &apos;title&apos; por &apos;name&apos;: solo cambia esta línea.\n// Ningún otro fichero toca los campos crudos de la API.',
    p3Insight:       '<strong>Por qué importa:</strong> sin un mapper, el conocimiento de los campos de la API se filtra a cualquier clase que procese la respuesta. Con el engine, <code>StationDto::fromApiData()</code> es el único punto de contacto. Si la API cambia un campo, hay exactamente un sitio que tocar.',

    // Pattern 4
    p4Title:       'Anti-Corruption Layer',
    p4Anti:        '&#10007; El controller importa directamente el cliente HTTP. Cambiar de proveedor de API implica tocar cada controller que la consume.',
    p4Sol:         '&#10003; <code>StationService</code> es la única frontera entre el dominio y la integración. Los controllers solo ven objetos del dominio propio.',
    p4CmMapsConv:  '// mapea convenciones privadas _hasPhoto, _photoUrl...',
    p4CmSwitchBad: '// Cambias de API &rarr; tocas este controller,\n// y todos los demás que hagan lo mismo.',
    p4CmSwitchGood:'// Cambias de API &rarr; StationService absorbe el cambio.\n// Este controller no cambia.',
    p4Insight:     '<strong>Por qué importa:</strong> sin ACL, el controller está acoplado a <code>RailwayApiService</code> y sus convenciones privadas (<code>_hasPhoto</code>). Con <code>StationService</code> como única frontera, los controllers solo importan objetos del dominio y el coste de cambiar de proveedor queda reducido a un solo fichero.',

    // Pattern 5
    p5Title:        'Batch de peticiones',
    p5Anti:         '&#10007; El <code>foreach</code> secuencial bloquea: cada petición espera a que termine la anterior. El tiempo total escala linealmente.',
    p5Sol: "&#10003; <code>sendManyOrFail()</code> agrupa peticiones mediante un cliente con soporte de lotes. El adaptador REST incluido utiliza respuestas HTTP lazy para la concurrencia.",
    p5CmBlocked:    '// petición HTTP &mdash; las demás esperan aquí bloqueadas',
    p5CmBatchBad: "// Cada petición espera la respuesta anterior.",
    p5CmAllSame: "// Concurrente cuando el adaptador y la cadena de middleware admiten lotes",
    p5CmBatchGood: "// Inspecciona cada resultado con sendMany(), o propaga el fallo al terminar con sendManyOrFail().",
    p5Insight:      '<strong>Por qué importa:</strong> los fallos individuales nunca abortan el batch &mdash; cada clave se resuelve de forma independiente. <code>sendMany()</code> devuelve un <code>BatchResultCollection</code> donde inspeccionas cada resultado; <code>sendManyOrFail()</code> lanza en el primer fallo después de que todo el batch haya ejecutado. El cliente REST por defecto ya implementa <code>BatchClientInterface</code> mediante las lazy responses de Symfony HttpClient &mdash; cero configuración adicional.',

    // Social Proof section
    proofEyebrow: "Evidencia técnica",
    proofH2: "Calidad que puedes comprobar.",
    proofItems: [
        {
            "stat": "Puertas de calidad",
            "desc": "PHPStan al máximo nivel, tests unitarios y tests de mutación. Consulta los resultados en el repositorio."
        },
        {
            "stat": "REST · GraphQL · Form",
            "desc": "Adaptadores incluidos, configurados por integración, con soporte para tus propios clientes."
        },
        {
            "stat": "Peticiones concurrentes",
            "desc": "Los clientes con soporte de lotes pueden solapar llamadas HTTP. El middleware de petición usa ejecución secuencial."
        },
        {
            "stat": "Experiencia en producción",
            "desc": "Destilado de integraciones en logística y viajes."
        }
    ],


    // Webhook showcase section
    webhookEyebrow: 'Webhooks Inbound',
    webhookH2:      'Recibe de cualquier plataforma.',
    webhookSub:     '<strong>v7.0:</strong> El componente Webhook de Symfony, ya montado. Un parser verifica la firma y decodifica el payload; tu listener recibe un evento tipado, no un array crudo. `make:webhook` escribe el parser, el mapper, el DTO y el consumer en tu app: las clases que conocen a tu proveedor son tuyas, no del bundle.',
    webhookPlatforms: "HMAC hexadecimal &nbsp;•&nbsp; HMAC base64 &nbsp;•&nbsp; HMAC con timestamp",
    webhookFeatures: [
        'Esquemas de firma cubiertos: HMAC hex tras prefijo, HMAC crudo en base64, HMAC con timestamp',
        'Eventos tipados en tus listeners, nunca un array crudo',
        'Una clase parser por tipo de evento &mdash; sin controller que escribir',
        'Async cuando quieras: Symfony entrega el evento a Messenger',
        'Detección de duplicados por fingerprint del payload, sobre tu almacenamiento',
    ],
    webhookBtn:     'Ver guía de webhooks →',

    // Observability section
    obsEyebrow: 'Observabilidad',
    obsH2:      'Monitoreo de producción incluido.',
    obsSub: "Los eventos de ciclo de vida exponen metadatos escalares acotados para peticiones, mapeos, fallos, renovación de tokens y webhooks. Los engines gestionados por el bundle publican mediante el event dispatcher de Symfony; la aplicación aporta almacenamiento y exportadores de métricas.",
    obsFeatures: [
        'Metadatos escalares: sin objetos request, response, token ni Throwable',
        'Duración lógica de operación, status y clase de respuesta/excepción',
        'Soporte #[AsEventListener] de Symfony para engines gestionados por el bundle',
        'LifecycleEventDispatcher para wiring manual/compartido explícito',
        'ObservabilitySetup opcional para logging, métricas y alertas',
    ],
    obsBtn:     'Ver guía de observabilidad →',

    // Wiki section
    wikiEyebrow:   'Aprende y Consulta',
    wikiH2:        'Documentación completa y guías.',
    wikiSub:       'Configuración, patrones, testing, troubleshooting. Architecture Decision Records explicando cada decisión de diseño. Guías de migración para cada actualización.',
    wikiFeatures: [
        { title: 'Getting Started', desc: 'Guía de inicio rápido, instalación y uso básico', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/getting-started/README.md' },
        { title: 'Architecture', desc: 'Abstracciones, flujo de datos y patrones de diseño', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/ARCHITECTURE.md' },
        { title: 'Testing', desc: 'Estructura de tests, estrategias y ejecución', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/TESTING.md' },
        { title: 'Lifecycle Events', desc: 'Eventos escalares de observabilidad y semántica temporal', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/LIFECYCLE.md' },
        { title: 'Observability', desc: 'Listeners Symfony, wiring del helper, métricas y logging', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/OBSERVABILITY.md' },
        { title: 'Webhooks', desc: 'Webhooks autenticados e idempotencia propiedad de la aplicación', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/docs/WEBHOOK.md' },
    ],
    wikiBtn:     'Explorar documentación →',

    // Thanks section
    thanksEyebrow: 'Antes de irte',
    thanksH2:      'Gracias por llegar hasta aquí.',
    thanksP: "Este bundle está destilado de integraciones en producción en logística y viajes. Hice el patrón explícito, lo probé y abrí el código para que otros equipos puedan utilizarlo y mejorarlo.",

    // CTA
    ctaEyebrow:   'Contacto',
    ctaH2:        'Empieza a construir tu próxima integración hoy.',
    ctaSub: "Escríbeme, abre una GitHub Discussion o instala el bundle y pruébalo.",
    ctaEmailLabel: "Envíame un email",
    ctaEmail:     'hola@integrationengine.dev',
    ctaEmailHref: 'mailto:hola@integrationengine.dev',
    ctaDiscuss:   'Unirse a Discussions',
};
