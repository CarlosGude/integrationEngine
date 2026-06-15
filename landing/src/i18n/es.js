export default {
    lang: 'es',

    // Nav
    navProblem: 'El Problema',
    navPattern:  'El Patrón',

    // Hero
    heroH1:  'Tus integraciones externas<br>merecen un patrón',
    heroP:   'Sin estructura, cada API acaba con su propia forma, su propia lógica, su propia deuda técnica. Al cabo de unos meses no tienes integraciones. Tienes un zoo.',
    heroBtn1: 'Explorar el patrón',
    heroBtn2: 'Código fuente',

    // Install box
    copyHint: '¡Copiado!',

    // Problem section
    problemEyebrow: 'El Problema',
    problemH2:  'Por qué las integraciones degeneran',
    problemSub: 'Sin un patrón, la entropía gana. Cada integración introduce sus propias convenciones y el código se vuelve arqueología.',
    problems: [
        { title: 'God class inevitable',  desc: 'Sin un punto de entrada definido, los métodos HTTP, el parseo y la lógica se acumulan en una sola clase. Imposible de testear, imposible de escalar.' },
        { title: 'Contratos implícitos',  desc: 'Las respuestas viajan como <code>array&lt;string, mixed&gt;</code>. Cada capa que las toca tiene que conocer los nombres exactos de los campos de la API.' },
        { title: 'Batch secuencial',      desc: 'El <code>foreach</code> bloquea: cada petición espera a la anterior. 10 items = 10&times; el tiempo de uno. No escala, y no hay nada que te lo avise.' },
    ],

    // Structure section
    structureEyebrow:   'La Solución',
    structureH2:        'Una estructura predecible para cada integración',
    structureSub:       'Si conoces una integración, conoces todas. El engine impone la misma forma en cada API que integras.',
    structureYamlHdr:   'MAPA DE ACCIONES (YAML)',
    structureDirHdr:    'DIRECTORIO TIPO',

    // Structure code comments
    cmContract:  '# RailwayStations.yaml &mdash; contrato visible de un vistazo',
    cmFacade:    '&larr; fachada',
    cmActionMap: '&larr; mapa de acciones',

    // Pattern section
    patternEyebrow: 'El Patrón',
    patternH2:      'Cinco antipatrones que el engine resuelve',
    patternSub:     'Los mismos endpoints, dos implementaciones. Cada sección muestra las clases reales del proyecto.',
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
    p3Title:        'Mapeo de la respuesta',
    p3Anti:         '&#10007; Los campos crudos de la API (<code>\'title\'</code>, <code>\'photos\'</code>, <code>\'photoBaseUrl\'</code>) se filtran a todas las capas. Si la API cambia un nombre de campo, el error aparece en múltiples ficheros.',
    p3Sol:          '&#10003; Un único <code>Mapper</code> accede a los campos crudos. El resto del código habla con DTOs tipados.',
    p3CmRawField:   '// campo crudo de la API',
    p3CmNotLng:     '// no &apos;lng&apos;, no &apos;longitude&apos;',
    p3CmPrivate:    '// convención privada',
    p3CmRenameEvery:'// Si la API cambia &apos;title&apos; por &apos;name&apos;: hay que buscar y corregir\n// en TODOS los ficheros que acceden al array. ¿Cuántos son?',
    p3CmOnlyPlace:  '// único sitio',
    p3CmRenameOne:  '// Si la API cambia &apos;title&apos; por &apos;name&apos;: solo cambia esta línea.\n// Ningún otro fichero toca los campos crudos de la API.',
    p3Insight:      '<strong>Por qué importa:</strong> sin un mapper, el conocimiento de los campos de la API se filtra a cualquier clase que procese la respuesta. Con el engine, <code>StationDto::fromApiData()</code> es el único punto de contacto. Si la API cambia un campo, hay exactamente un sitio que tocar.',

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
    p5Insight:      '<strong>Por qué importa:</strong> <code>sendManyOrFail()</code> despacha en paralelo. El cliente REST por defecto ya implementa <code>BatchClientInterface</code> &mdash; cero configuración adicional. Si una petición falla, la excepción identifica exactamente cuál, y el resto del batch ya se ha ejecutado.',

    // CTA
    ctaEyebrow:   'Contacto',
    ctaH2:        '¿Preguntas? ¿Ideas? ¿Feedback?',
    ctaSub:       'Escríbenos directamente o únete a la conversación en GitHub Discussions.',
    ctaEmailLabel:'Envíanos un email',
    ctaEmail:     'hola@integration.dev',
    ctaEmailHref: 'mailto:hola@integration.dev',
    ctaDiscuss:   'Unirse a Discussions',

    // Summary
    summaryEyebrow: 'Resumen',
    summaryH2:      'Sin patrón vs Engine pattern',
    summaryThConcept: 'Concepto',
    summaryThWithout: 'Sin patrón',
    summaryThEngine:  'Engine pattern',
    summaryRows: [
        { concept: 'Declaración de endpoints',    without: '&#10007; Dispersa en métodos de la God class',                 engine: '&#10003; Un fichero YAML por integración' },
        { concept: 'Construcción de URLs',         without: '&#10007; Concatenación de strings, falla en silencio',         engine: '&#10003; <code>{placeholders}</code> validados en tiempo de ejecución' },
        { concept: 'Campos de la API en el código',without: '&#10007; Filtrados a todas las capas',                         engine: '&#10003; Encapsulados en <code>Mapper</code> + <code>DTO</code>' },
        { concept: 'Tipo de retorno',              without: '&#10007; <code>array&lt;string,&nbsp;mixed&gt;</code> + convenciones <code>_</code>', engine: '&#10003; <code>ResponseInterface</code> tipada' },
        { concept: 'Anti-Corruption Layer',        without: '&#10007; No existe &mdash; controller acoplado al cliente HTTP', engine: '&#10003; <code>StationService</code> como única frontera' },
        { concept: 'Auth (Bearer, Basic, OAuth2)', without: 'Headers manuales en cada <code>request()</code>',              engine: '&#10003; Declarado en YAML, gestionado por el engine' },
        { concept: 'Añadir un nuevo endpoint',     without: 'Método + URL + parseo + mapeo dispersos',                      engine: '&#10003; Action + Mapper + Response + 3 líneas YAML' },
        { concept: 'Batch',                        without: '&#10007; <code>foreach</code> secuencial, tiempo lineal',       engine: '&#10003; <code>sendManyOrFail()</code>, tiempo constante' },
    ],
};
