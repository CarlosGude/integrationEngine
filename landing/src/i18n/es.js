export default {
    lang: 'es',

    // Nav
    navProblem: 'El Problema',
    navPattern:  'El Patrón',
    navExample:  'Ejemplo',
    navWebhooks: 'Webhooks',

    // Hero
    heroH1:  'Deja de escribir código de integración dos veces.',
    heroP:   'Cada integración que entrega tu equipo sigue el mismo estándar predecible. Envía requests a APIs externas o recibe webhooks de ellas &mdash; ambas con la autenticación por token resuelta, ejecución en paralelo y DTOs tipados. Los desarrolladores nuevos entienden cualquier API en minutos &mdash; no en días. Un solo bundle de Symfony &mdash; destilado de experiencia en producción a escala.',
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
        { stat: 'Días &rarr; Horas',  desc: 'Tiempo desde cero hasta una integración funcionando y testeada &mdash; con la auth, las llamadas en paralelo y las respuestas tipadas incluidas.' },
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
    parallelSub:          '<code>sendManyOrFail()</code> despacha todas las peticiones en paralelo. El tiempo total &asymp; la petición más lenta &mdash; independientemente de cuántas envíes. Una búsqueda de disponibilidad en un marketplace de viajes son 4 consultas en paralelo para una ciudad pequeña y 17 para una grande &mdash; por cliente. Esto lo gestiona.',
    parallelBefore:       'Secuencial (foreach)',
    parallelAfter:        'En paralelo (sendManyOrFail)',
    parallelBeforeTime:   '4,2s',
    parallelAfterTime:    '0,8s',
    parallelBeforeDetail: '10 peticiones &times; 420ms cada una',
    parallelAfterDetail:  '10 peticiones, ejecución concurrente',

    // Stripe example section
    stripeEyebrow: 'Ejemplo real',
    stripeH2:      'Una integración con Stripe en menos de 30 líneas.',
    stripeSub:     'Un entry en YAML. Un mapper. Una respuesta tipada. El token se pide, se cachea y se vuelve a pedir ante un 401 &mdash; declarado en YAML, no escrito en tu aplicación.',
    stripeBtn:     'Ver código en GitHub',

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
    patternSub:           'Los mismos endpoints, dos implementaciones. Cada sección muestra las clases reales del proyecto.',
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
    p2Sol:         '&#10003; Plantillas <code>{placeholder}</code> en el YAML resueltas por <code>DefaultActionContext</code>. El engine lanza excepción inmediata si falta un parámetro.',
    p2CmOneParam:  '// Un parámetro en la ruta',
    p2CmTwoParam:  '// Dos parámetros en la ruta',
    p2CmNull:      '// Si $stationId === null:\n// &rarr; /photoStationById/de/\n// &rarr; HTTP 404 sin excepción descriptiva.\n// El error aparece tarde, lejos del origen.',
    p2CmMissing:   '// Si falta &apos;stationId&apos;: excepción inmediata y descriptiva\n// antes de que se haga la llamada HTTP.',
    p2Insight:     '<strong>Por qué importa:</strong> la concatenación de strings falla en silencio. Los placeholders del engine son contratos: si falta uno, el error es inmediato y descriptivo, no un 404 misterioso dos capas más abajo.',

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
    p5Sol:          '&#10003; <code>sendManyOrFail()</code> despacha todas en paralelo. El tiempo total &asymp; la petición más lenta, independientemente del número de items.',
    p5CmBlocked:    '// petición HTTP &mdash; las demás esperan aquí bloqueadas',
    p5CmBatchBad:   '//  3 estaciones &times; 250ms = ~750ms\n// 10 estaciones &times; 250ms = ~2500ms  &larr; escala linealmente',
    p5CmAllSame:    '// todas salen al mismo tiempo &mdash; tiempo total &asymp; la más lenta',
    p5CmBatchGood:  '//  3 estaciones &rarr; ~250ms   (la más lenta, no la suma)\n// 10 estaciones &rarr; ~250ms   (no escala)',
    p5Insight:      '<strong>Por qué importa:</strong> los fallos individuales nunca abortan el batch &mdash; cada clave se resuelve de forma independiente. <code>sendMany()</code> devuelve un <code>BatchResultCollection</code> donde inspeccionas cada resultado; <code>sendManyOrFail()</code> lanza en el primer fallo después de que todo el batch haya ejecutado. El cliente REST por defecto ya implementa <code>BatchClientInterface</code> mediante las lazy responses de Symfony HttpClient &mdash; cero configuración adicional.',

    // Social Proof section
    proofEyebrow: 'Construido para Producción',
    proofH2:      'Probado a escala.',
    proofItems: [
        { stat: '601 tests',      desc: 'PHPStan level max, 100% de mutation score, todos los gates en verde en CI.' },
        { stat: 'REST · GraphQL', desc: 'Dos adapters de cliente incluidos, webhooks inbound, y un tag para el tuyo.' },
        { stat: '5–13x más rápido', desc: 'Ejecución paralela por defecto: el tiempo total es la llamada más lenta, no la suma de todas.' },
        { stat: 'Nacido de trabajo real', desc: 'Destilado de integraciones de viajes y e-commerce que el autor entregó y mantuvo.' },
    ],

    // Status section
    statusEyebrow:      'Estado',
    statusH2:           'v7.0.1 en vivo — Clientes por integración, pipeline de middleware, firma de requests',
    statusP:            'Integraciones salientes (Stripe, Shopify, etc.) + webhooks inbound (recibe eventos de plataformas externas). Verificación de firmas y eventos tipados incluidos; la detección de duplicados es un servicio sobre el almacenamiento que tú pongas. Sin promesas con fecha &mdash; solo software funcionando y transparencia en cada hito.',
    statusNow:          '<strong>Ahora:</strong> v7.0.1 publicada.<br/>• Clientes por integración con middleware flexible<br/>• Firma de requests para OAuth 1.0a, AWS SigV4<br/>• Soporte multi-inquilino vía connection resolver<br/>• 601 tests, 100% de mutation score<br/>• Compatible con PHP 8.4<br/>• <a href="https://github.com/CarlosGude/integrationEngine/wiki" target="_blank">Wiki completa</a> y guías de migración incluidas',
    statusNext:         '<strong>Próximo:</strong> Fase 7: Admin experience (comandos de consola para inspeccionar integraciones, exports de Prometheus).',
    statusLater:        '<strong>Después:</strong> Ecosistema de plugins open-source, patrones de migración legacy, materiales de formación certificada.',
    statusRoadmapLink:  'Ver fases: ahora, próximo, después →',

    // Webhook showcase section
    webhookEyebrow: 'Webhooks Inbound',
    webhookH2:      'Recibe de cualquier plataforma.',
    webhookSub:     '<strong>v7.0:</strong> El componente Webhook de Symfony, ya montado. Un parser verifica la firma y decodifica el payload; tu listener recibe un evento tipado, no un array crudo. `make:webhook` escribe el parser, el mapper, el DTO y el consumer en tu app: las clases que conocen a tu proveedor son tuyas, no del bundle.',
    webhookPlatforms: 'Shopify &nbsp;•&nbsp; WooCommerce &nbsp;•&nbsp; Stripe &nbsp;•&nbsp; Custom',
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
    obsSub:     '<strong>v5.3.0+:</strong> Lifecycle events (ActionStarted, HttpResponseReceived, ResponseMapped, ActionCompleted, ActionFailed) se disparan en cada etapa. Trackea latencia HTTP vs. transformación a DTO por separado. Un comando para wire logging, métricas Prometheus, error tracking Sentry, o audit trails custom. Cero overhead en producción (0.06ms por llamada, imperceptible).',
    obsFeatures: [
        'Timing detallado: duración HTTP call + duración DTO mapping',
        'Lifecycle events con metadata (action, integration, duration, status)',
        'LifecycleEventDispatcher para suscripción directa',
        'Adaptador SymfonyEventDispatcher (#[AsEventListener])',
        'ObservabilitySetup helper (logging + Prometheus + Sentry)',
    ],
    obsBtn:     'Ver guía de observabilidad →',

    // Wiki section
    wikiEyebrow:   'Aprende y Consulta',
    wikiH2:        'Wiki completa con guías y referencia de API.',
    wikiSub:       'Documentación completa sobre configuración, patrones, testing y troubleshooting. Architecture Decision Records (ADRs), guías de migración y ejemplos del mundo real.',
    wikiFeatures: [
        'DOCUMENTATION.md — Configuración, todas las opciones, flujo de datos',
        'ARCHITECTURE.md — Decisiones de diseño y patrones',
        'GitHub Wiki — Guías paso a paso y FAQs',
        'ADRs — Por qué lo construimos así (docs/adr/)',
        'TESTING.md — Estrategia de tests y estructura de suite',
        'Guías de MIGRATION — v6→v7 y otros upgrades',
    ],
    wikiBtn:     'Explorar la wiki →',

    // Thanks section
    thanksEyebrow: 'Antes de irte',
    thanksH2:      'Gracias por llegar hasta aquí.',
    thanksP:       'Este patrón salió de años integrando APIs de viajes y hosting &mdash; búsquedas de disponibilidad que se abren en 17 consultas paralelas por cliente, flujos de reserva, facturación. Una versión anterior de este engine aguantaba esa carga. Este bundle es lo que ese trabajo me enseñó: hecho explícito, testeado y abierto.',

    // CTA
    ctaEyebrow:   'Contacto',
    ctaH2:        'Empieza a construir tu próxima integración hoy.',
    ctaSub:       'Escríbenos directamente, abre una GitHub Discussion, o instálalo y pruébalo.',
    ctaEmailLabel:'Envíanos un email',
    ctaEmail:     'hola@integrationengine.dev',
    ctaEmailHref: 'mailto:hola@integrationengine.dev',
    ctaDiscuss:   'Unirse a Discussions',
};
