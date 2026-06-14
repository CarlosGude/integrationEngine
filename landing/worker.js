export default {
    async fetch(request, env, ctx) {
        const url  = new URL(request.url);
        const lang = url.searchParams.get('lang') === 'es' ? 'es' : 'en';
        console.info({ message: 'IntegrationEngine landing', lang });
        return new Response(getHTML(lang), {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
};

/* ─────────────────────────────────────────────────────────────
   TRADUCCIONES
───────────────────────────────────────────────────────────── */
const T = {
    es: {
        htmlLang: 'es',
        pageTitle: 'IntegrationEngine — Symfony Bundle',
        metaDesc:  'Un estándar para cada API externa en tus proyectos Symfony. Entrega más rápido. Onboarding instantáneo. Sin arqueología de código.',
        heroBadge: 'Symfony Bundle · PHP 8.2+ · Packagist',
        heroH1:    'Después de unos meses, ya no tienes integraciones. Tienes un zoo.',
        heroP:     'IntegrationEngine obliga a que todas las integraciones tengan la misma forma. Entrega más rápido. Onboarding instantáneo. Sin arqueología de código.',
        copyHint:  '¡Copiado!',
        heroNav: [
            { label: 'GitHub',        href: 'https://github.com/CarlosGude/integrationEngine' },
            { label: 'Packagist',     href: 'https://packagist.org/packages/carlosgude/integration-engine' },
            { label: 'Documentación', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION_ES.md' },
            { label: 'Demo',          href: 'https://github.com/CarlosGude/integrationEngine-use-example' },
        ],
        whyLabel: '¿Por qué no HttpClient?',
        whyH2:    'HttpClient es una herramienta. IntegrationEngine es un estándar.',
        whySub:   'Usa HttpClient para llamadas puntuales. Usa IntegrationEngine cuando las APIs externas son parte de tu arquitectura — y necesitas que todo tu equipo hable el mismo idioma seis meses después.',
        makeLabel: 'Un comando lo genera todo',
        makeH2:   'De cero a una integración lista para producción en un solo comando.',
        makeSub:  'Responde tres preguntas. Obtén una integración completamente tipada y generada.',
        makeCmd:  '$ php bin/console make:integration MyApi GetEmployee',
        makeFiles: [
            'config/packages/integration_engine.yaml',
            'src/Infrastructure/Integrations/MyApi/MyApiIntegration.php',
            'src/Infrastructure/Integrations/MyApi/MyApi.yaml',
            'src/Infrastructure/Integrations/MyApi/GetEmployee/Request/GetEmployeeAction.php',
            'src/Infrastructure/Integrations/MyApi/GetEmployee/Response/GetEmployeeMapper.php',
            'src/Infrastructure/Integrations/MyApi/GetEmployee/Response/GetEmployeeResponse.php',
        ],
        codeReadmeLink: 'Para el patrón completo (facade → service → dominio) →',
        codeReadmeLinkLabel: 'README',
        problemaLabel: 'El Problema',
        problemaH2:    'Cada integración acaba siendo un caso aislado',
        problemaSub:   'Sin un estándar compartido, cada desarrollador lo resuelve a su manera. El código se fragmenta. El conocimiento desaparece. Cada nueva API supone empezar desde cero.',
        problems: [
            { h3: 'Días perdidos por cada API',    p: 'Sin un patrón común, conectar una nueva API cuesta días de descubrimiento y decisiones arbitrarias.' },
            { h3: 'Onboarding que no escala',      p: 'Una estructura diferente por integración obliga a cada nuevo compañero a empezar de cero.' },
            { h3: 'Bugs escondidos en los huecos', p: 'Lógica HTTP dispersa por los services, sin contrato que aplicar ni aislar en tests.' },
            { h3: 'Cero reutilización',            p: 'Auth, cache, mapeo — reinventados desde cero en cada integración, sin excepción.' },
            { h3: 'Nadie sabe cómo funciona',      p: 'Seis meses después, el desarrollador que la construyó se fue y el código es ilegible.' },
        ],
        comoLabel: 'Cómo Funciona',
        comoH2:    'Un punto de entrada. Un contrato. Siempre.',
        comoSub:   'Un único flujo para todas tus APIs externas. Cada paso tiene un responsable claro y testeable.',
        features: [
            { h3: 'Listo en minutos, no en días',       p: '<code>make:integration</code> genera Action, Mapper, Response DTO y YAML con un comando. Solo escribes lógica de negocio.' },
            { h3: 'Si conoces una, las conoces todas',  p: 'Cada integración sigue la misma estructura y los mismos contratos. Onboarding instantáneo para cada nuevo compañero.' },
            { h3: 'Tokens gestionados sin esfuerzo',    p: 'OAuth, sesiones, API keys — obtenidos, cacheados y refrescados automáticamente en 401. Sin lógica de tokens manual.' },
            { h3: 'Respuestas tipadas y garantizadas',  p: 'Cada endpoint devuelve un DTO garantizado. Sin sorpresas en tiempo de ejecución ni en producción.' },
            { h3: 'Requests en paralelo, de serie',     p: '<code>sendMany()</code> ejecuta N peticiones en paralelo. Los fallos individuales nunca abortan el batch.' },
            { h3: 'Intercambia cualquier capa',         p: 'Cliente HTTP, cache, fuente de config — cada uno sustituible con una línea en YAML.' },
        ],
        callsiteLabel: 'El Call Site',
        callsiteH2:    'La misma llamada. Siempre.',
        callsiteSub:   'Sin clientes HTTP, sin builders de peticiones, sin mappers. Un único método de facade que todo tu equipo reconoce.',
        tabs: [
            { label: 'Sin auth',           id: 'tab-noauth' },
            { label: 'Con path params',    id: 'tab-path' },
            { label: 'Con body y headers', id: 'tab-body' },
            { label: 'GraphQL',            id: 'tab-graphql' },
            { label: 'Batch / sendMany',   id: 'tab-batch' },
        ],
        codeNoAuth:   `<span class="cmt">// Action path: GET /employees</span>\n<span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(\n    <span class="cls">GetEmployeesAction</span><span class="kw">::</span><span class="met">getName</span>()\n);\n<span class="cmt">// → GET /employees</span>`,
        codePath:     `<span class="cmt">// Action path: GET /employees/{id}</span>\n<span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(\n    actionName: <span class="cls">GetEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    context: <span class="cls">DefaultActionContext</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),\n);\n<span class="cmt">// → GET /employees/42</span>\n\n\\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetEmployeeResponse</span>);\n<span class="cmt">// GetEmployeeResponse { id: 42, name: 'John Doe', department: 'Engineering' }</span>`,
        codeBody:     `<span class="cmt">// Action path: POST /employees</span>\n<span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(\n    actionName: <span class="cls">CreateEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    body: <span class="cls">CreateEmployeeBody</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'name'</span> <span class="kw">=&gt;</span> <span class="str">'John Doe'</span>]),\n    headers: <span class="kw">new</span> <span class="cls">CorrelationHeaders</span>(<span class="var">$correlationId</span>),\n);\n<span class="cmt">// → POST /employees { "name": "John Doe" }</span>\n\n\\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">CreateEmployeeResponse</span>);\n<span class="cmt">// CreateEmployeeResponse { id: 99, name: 'John Doe', status: 'active' }</span>`,
        codeGraphQL:  `<span class="cmt">// Action endpoint: POST /graphql</span>\n<span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(\n    actionName: <span class="cls">GetUserAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    body: <span class="cls">GetUserBody</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),\n);\n<span class="cmt">// → POST /graphql { "query": "query { user(id: $id) { name } }", "variables": { "id": 42 } }</span>\n\n\\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetUserResponse</span>);\n<span class="cmt">// GetUserResponse { id: 42, name: 'John Doe' }</span>`,
        codeSendMany: `<span class="cmt">// Fan-out paralelo — una petición por ID de empleado</span>\n<span class="var">$requests</span> = [];\n<span class="kw">foreach</span> (<span class="var">$ids</span> <span class="kw">as</span> <span class="var">$id</span>) {\n    <span class="var">$requests</span>[<span class="var">$id</span>] = <span class="cls">EngineRequest</span><span class="kw">::</span><span class="met">create</span>(\n        <span class="cls">GetEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),\n        <span class="cls">DefaultActionContext</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),\n    );\n}\n\n<span class="var">$results</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">sendMany</span>(<span class="var">$requests</span>);\n<span class="cmt">// BatchResultCollection — cada clave se resuelve de forma independiente</span>\n\n<span class="kw">if</span> (<span class="var">$results</span><span class="kw">-&gt;</span><span class="met">hasFailures</span>()) {\n    <span class="kw">throw</span> <span class="met">array_values</span>(<span class="var">$results</span><span class="kw">-&gt;</span><span class="met">errors</span>())[<span class="num">0</span>];\n}\n\n<span class="kw">return</span> <span class="met">array_map</span>(\n    <span class="kw">fn</span>(<span class="var">$dto</span>) <span class="kw">=&gt;</span> <span class="cls">Employee</span><span class="kw">::</span><span class="met">fromDto</span>(<span class="var">$dto</span>),\n    <span class="var">$results</span><span class="kw">-&gt;</span><span class="met">responses</span>(),\n);`,
        capasLabel: 'Diseño en Capas',
        capasH2:    'El bundle propone. No impone.',
        capasSub:   'Tres niveles que emergen solos. Usa los que necesites.',
        layersHead: ['Clase', 'Responsabilidad', 'Alcance'],
        layers: [
            { name: 'GetEmployeeAction', desc: 'Solo declara el método, el path y el DTO de respuesta. Sin lógica HTTP.',                   scope: 'Acción concreta' },
            { name: 'MyApiIntegration',  desc: 'Auth, URL base y headers comunes de MyApi. Reutilizado por todas sus acciones.',            scope: 'Integración' },
            { name: 'AbstractAction',    desc: 'Contrato base que provee el engine. Extensible sin tocar el core.',                        scope: 'Bundle' },
        ],
        makeNote: 'El comando <code>make:integration</code> crea el config, las clases y el YAML en un solo paso.',
        ctaH2:   'Estandariza tus integraciones hoy.',
        ctaP:    'Instalación en 30 segundos. Primera integración tipada en menos de 5 minutos.',
        ctaBtn1: 'Ver en GitHub',
        ctaBtn2: 'Documentación',
        ctaBtn3: 'Ver demo',
        ctaBtn3Href: 'https://github.com/CarlosGude/integrationEngine-use-example',
        footerTxt: 'IntegrationEngine &mdash; <a href="https://github.com/CarlosGude/integrationEngine">CarlosGude/integrationEngine</a> &mdash; MIT License',
    },

    en: {
        htmlLang: 'en',
        pageTitle: 'IntegrationEngine — Symfony Bundle',
        metaDesc:  'One standard for every external API in your Symfony projects. Ship faster. Onboard instantly. Stop doing archaeology.',
        heroBadge: 'Symfony Bundle · PHP 8.2+ · Packagist',
        heroH1:    'After a few months, you no longer have integrations. You have a zoo.',
        heroP:     'IntegrationEngine forces every integration to look the same. Ship faster. Onboard instantly. Stop doing archaeology forever.',
        copyHint:  'Copied!',
        heroNav: [
            { label: 'GitHub',        href: 'https://github.com/CarlosGude/integrationEngine' },
            { label: 'Packagist',     href: 'https://packagist.org/packages/carlosgude/integration-engine' },
            { label: 'Documentation', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION.md' },
            { label: 'Demo',          href: 'https://github.com/CarlosGude/integrationEngine-use-example' },
        ],
        whyLabel: 'Why not HttpClient?',
        whyH2:    'HttpClient is a tool. IntegrationEngine is a standard.',
        whySub:   'Use HttpClient for one-off calls. Use IntegrationEngine when external APIs are part of your architecture — and you need your whole team to speak the same language six months from now.',
        makeLabel: 'One command generates everything',
        makeH2:   'From zero to a production-ready integration in one command.',
        makeSub:  'Answer three questions. Get a fully scaffolded, typed integration.',
        makeCmd:  '$ php bin/console make:integration MyApi GetEmployee',
        makeFiles: [
            'config/packages/integration_engine.yaml',
            'src/Infrastructure/Integrations/MyApi/MyApiIntegration.php',
            'src/Infrastructure/Integrations/MyApi/MyApi.yaml',
            'src/Infrastructure/Integrations/MyApi/GetEmployee/Request/GetEmployeeAction.php',
            'src/Infrastructure/Integrations/MyApi/GetEmployee/Response/GetEmployeeMapper.php',
            'src/Infrastructure/Integrations/MyApi/GetEmployee/Response/GetEmployeeResponse.php',
        ],
        codeReadmeLink: 'For the full pattern (facade → service → domain) →',
        codeReadmeLinkLabel: 'README',
        problemaLabel: 'The Problem',
        problemaH2:    'Every integration ends up as an isolated case',
        problemaSub:   'Without a shared standard, each developer solves it differently. The codebase fragments. Knowledge disappears. Every new API means starting from scratch.',
        problems: [
            { h3: 'Days lost per API',          p: 'No shared pattern means days of discovery and arbitrary decisions every time a new API lands on the backlog.' },
            { h3: 'Onboarding that never scales', p: 'A different structure per integration means every new teammate has to start from zero, every time.' },
            { h3: 'Bugs hiding in the gaps',    p: 'HTTP logic scattered across services, no contract to enforce, nothing to test in isolation.' },
            { h3: 'Zero reuse',                 p: 'Auth, caching, mapping — reinvented from scratch every single time, in every integration.' },
            { h3: 'Nobody knows how it works',  p: 'Six months later, the developer who built it is gone and the code is unreadable.' },
        ],
        comoLabel: 'How It Works',
        comoH2:    'One entry point. One contract. Every time.',
        comoSub:   'A single flow for all your external APIs. Every step has a clear, testable owner.',
        features: [
            { h3: 'Ship in minutes, not days',       p: '<code>make:integration</code> scaffolds Action, Mapper, Response DTO and YAML in one command. You write only business logic.' },
            { h3: 'If you know one, you know them all', p: 'Every integration follows the same layout and the same contracts. Instant onboarding for every new teammate.' },
            { h3: 'Token management, zero effort',   p: 'OAuth, sessions, API keys — fetched, cached, and auto-refreshed on 401. No manual token logic, ever.' },
            { h3: 'Type-safe responses',             p: 'Every endpoint returns a guaranteed DTO. No guessing at runtime, no silent surprises in production.' },
            { h3: 'Parallel requests, built-in',     p: '<code>sendMany()</code> runs N concurrent requests. Individual failures never abort the batch.' },
            { h3: 'Swap any layer',                  p: 'HTTP client, cache backend, config source — each replaceable with one line in YAML.' },
        ],
        callsiteLabel: 'The Call Site',
        callsiteH2:    'The same call. Every time.',
        callsiteSub:   'No HTTP clients. No request builders. No mappers. One clean facade method your entire team recognises.',
        tabs: [
            { label: 'No auth',          id: 'tab-noauth' },
            { label: 'Path params',      id: 'tab-path' },
            { label: 'Body and headers', id: 'tab-body' },
            { label: 'GraphQL',          id: 'tab-graphql' },
            { label: 'Batch / sendMany', id: 'tab-batch' },
        ],
        codeNoAuth:   `<span class="cmt">// Action path: GET /employees</span>\n<span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(\n    <span class="cls">GetEmployeesAction</span><span class="kw">::</span><span class="met">getName</span>()\n);\n<span class="cmt">// → GET /employees</span>`,
        codePath:     `<span class="cmt">// Action path: GET /employees/{id}</span>\n<span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(\n    actionName: <span class="cls">GetEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    context: <span class="cls">DefaultActionContext</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),\n);\n<span class="cmt">// → GET /employees/42</span>\n\n\\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetEmployeeResponse</span>);\n<span class="cmt">// GetEmployeeResponse { id: 42, name: 'John Doe', department: 'Engineering' }</span>`,
        codeBody:     `<span class="cmt">// Action path: POST /employees</span>\n<span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(\n    actionName: <span class="cls">CreateEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    body: <span class="cls">CreateEmployeeBody</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'name'</span> <span class="kw">=&gt;</span> <span class="str">'John Doe'</span>]),\n    headers: <span class="kw">new</span> <span class="cls">CorrelationHeaders</span>(<span class="var">$correlationId</span>),\n);\n<span class="cmt">// → POST /employees { "name": "John Doe" }</span>\n\n\\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">CreateEmployeeResponse</span>);\n<span class="cmt">// CreateEmployeeResponse { id: 99, name: 'John Doe', status: 'active' }</span>`,
        codeGraphQL:  `<span class="cmt">// Action endpoint: POST /graphql</span>\n<span class="var">$response</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">send</span>(\n    actionName: <span class="cls">GetUserAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    body: <span class="cls">GetUserBody</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),\n);\n<span class="cmt">// → POST /graphql { "query": "query { user(id: $id) { name } }", "variables": { "id": 42 } }</span>\n\n\\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetUserResponse</span>);\n<span class="cmt">// GetUserResponse { id: 42, name: 'John Doe' }</span>`,
        codeSendMany: `<span class="cmt">// Parallel fan-out — one request per employee ID</span>\n<span class="var">$requests</span> = [];\n<span class="kw">foreach</span> (<span class="var">$ids</span> <span class="kw">as</span> <span class="var">$id</span>) {\n    <span class="var">$requests</span>[<span class="var">$id</span>] = <span class="cls">EngineRequest</span><span class="kw">::</span><span class="met">create</span>(\n        <span class="cls">GetEmployeeAction</span><span class="kw">::</span><span class="met">getName</span>(),\n        <span class="cls">DefaultActionContext</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="var">$id</span>]),\n    );\n}\n\n<span class="var">$results</span> = <span class="var">$this</span><span class="kw">-&gt;</span><span class="met">engine</span><span class="kw">-&gt;</span><span class="met">sendMany</span>(<span class="var">$requests</span>);\n<span class="cmt">// BatchResultCollection — each key resolves independently</span>\n\n<span class="kw">if</span> (<span class="var">$results</span><span class="kw">-&gt;</span><span class="met">hasFailures</span>()) {\n    <span class="kw">throw</span> <span class="met">array_values</span>(<span class="var">$results</span><span class="kw">-&gt;</span><span class="met">errors</span>())[<span class="num">0</span>];\n}\n\n<span class="kw">return</span> <span class="met">array_map</span>(\n    <span class="kw">fn</span>(<span class="var">$dto</span>) <span class="kw">=&gt;</span> <span class="cls">Employee</span><span class="kw">::</span><span class="met">fromDto</span>(<span class="var">$dto</span>),\n    <span class="var">$results</span><span class="kw">-&gt;</span><span class="met">responses</span>(),\n);`,
        capasLabel: 'Layered Design',
        capasH2:    'The bundle proposes. It does not impose.',
        capasSub:   'Three levels that emerge naturally. Use whichever you need.',
        layersHead: ['Class', 'Responsibility', 'Scope'],
        layers: [
            { name: 'GetEmployeeAction', desc: 'Only declares the method, path and response DTO. No HTTP logic.',                    scope: 'Concrete action' },
            { name: 'MyApiIntegration',  desc: 'Auth, base URL and common headers for MyApi. Reused by all its actions.',           scope: 'Integration' },
            { name: 'AbstractAction',    desc: 'Base contract provided by the engine. Extensible without touching the core.',       scope: 'Bundle' },
        ],
        makeNote: 'The <code>make:integration</code> command creates the config, classes and YAML in a single step.',
        ctaH2:   'Standardise your integrations today.',
        ctaP:    'Install in 30 seconds. Ship your first typed integration in under 5 minutes.',
        ctaBtn1: 'View on GitHub',
        ctaBtn2: 'Documentation',
        ctaBtn3: 'See demo',
        ctaBtn3Href: 'https://github.com/CarlosGude/integrationEngine-use-example',
        footerTxt: 'IntegrationEngine &mdash; <a href="https://github.com/CarlosGude/integrationEngine">CarlosGude/integrationEngine</a> &mdash; MIT License',
    },
};

/* ─────────────────────────────────────────────────────────────
   HTML BUILDER
───────────────────────────────────────────────────────────── */
function getHTML(lang) {
    const t = T[lang];

    const navLinks = t.heroNav.map(n =>
        `<a href="${n.href}" target="_blank" rel="noopener">${n.label}</a>`
    ).join('\n    ');

    const problems = t.problems.map(p => `
      <div class="problem-card">
        <h3>${p.h3}</h3>
        <p>${p.p}</p>
      </div>`).join('');

    const features = t.features.map(f => `
      <div class="feature-card">
        <h3>${f.h3}</h3>
        <p>${f.p}</p>
      </div>`).join('');

    const tabs = t.tabs.map((tab, i) =>
        `<button class="code-tab${i === 0 ? ' active' : ''}" onclick="showTab(this,'${tab.id}')">${tab.label}</button>`
    ).join('\n        ');

    const layerHead = t.layersHead.map(h => `<th>${h}</th>`).join('');
    const layers = t.layers.map(l => `
        <tr>
          <td><span class="layer-name">${l.name}</span></td>
          <td class="layer-desc">${l.desc}</td>
          <td class="layer-scope">${l.scope}</td>
        </tr>`).join('');

    return `<!DOCTYPE html>
<html lang="${t.htmlLang}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>${t.pageTitle}</title>
  <meta name="description" content="${t.metaDesc}" />
  <meta name="author" content="CarlosGude" />
  <meta property="og:type"        content="website" />
  <meta property="og:url"         content="https://integrationengine.dev/?lang=${lang}" />
  <meta property="og:title"       content="${t.pageTitle}" />
  <meta property="og:description" content="${t.metaDesc}" />
  <meta property="og:site_name"   content="IntegrationEngine" />
  <meta name="twitter:card"        content="summary" />
  <meta name="twitter:title"       content="${t.pageTitle}" />
  <meta name="twitter:description" content="${t.metaDesc}" />
  <link rel="canonical" href="https://integrationengine.dev/?lang=${lang}" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet" />
  <style>${CSS}</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="topnav">
  <a class="topnav-brand" href="?lang=${lang}">Integration<span>Engine</span></a>
  <div class="topnav-links">
    <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener">GitHub</a>
    <a href="${lang === 'es'
        ? 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION_ES.md'
        : 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION.md'
    }" target="_blank" rel="noopener">${lang === 'es' ? 'Documentación' : 'Documentation'}</a>
  </div>
  <div class="lang-pill">
    <a href="?lang=es" class="lang-opt ${lang === 'es' ? 'lang-active' : 'lang-inactive'}">
      <span class="lp-flag">🇪🇸</span>
      <span class="lp-string"><span class="lp-bracket">'</span><span class="lp-code">ES</span><span class="lp-bracket">'</span></span>
    </a>
    <a href="?lang=en" class="lang-opt ${lang === 'en' ? 'lang-active' : 'lang-inactive'}">
      <span class="lp-flag">🇬🇧</span>
      <span class="lp-string"><span class="lp-bracket">'</span><span class="lp-code">EN</span><span class="lp-bracket">'</span></span>
    </a>
  </div>
</nav>

<!-- HERO -->
<header class="hero">
  <p class="hero-badge">${t.heroBadge}</p>
  <h1>${t.heroH1}</h1>
  <p>${t.heroP}</p>
  <div class="install-box" onclick="copyInstall(this)">
    <span class="copy-hint">${t.copyHint}</span>
    composer require carlosgude/integration-engine
  </div>
  <a class="hero-gh-btn" href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener">${t.ctaBtn1} →</a>
</header>

<!-- POR QUÉ NO HTTPCLIENT -->
<section class="problema">
  <div class="container">
    <p class="section-label">${t.whyLabel}</p>
    <h2 class="section-title">${t.whyH2}</h2>
    <p class="section-sub">${t.whySub}</p>
  </div>
</section>

<!-- EL PROBLEMA -->
<section class="problema" style="padding-top:0">
  <div class="container">
    <p class="section-label">${t.problemaLabel}</p>
    <h2 class="section-title">${t.problemaH2}</h2>
    <p class="section-sub">${t.problemaSub}</p>
    <div class="problems-grid">${problems}
    </div>
  </div>
</section>

<!-- COMO FUNCIONA -->
<section class="como">
  <div class="container">
    <p class="section-label">${t.comoLabel}</p>
    <h2 class="section-title">${t.comoH2}</h2>
    <p class="section-sub">${t.comoSub}</p>
    <div class="pipeline">
      <div class="pipe-step"><span class="pipe-label">Registry</span><span class="pipe-arrow">→</span></div>
      <div class="pipe-step"><span class="pipe-label highlight">IntegrationEngine</span><span class="pipe-arrow">→</span></div>
      <div class="pipe-step"><span class="pipe-label">Action</span><span class="pipe-arrow">→</span></div>
      <div class="pipe-step"><span class="pipe-label">Auth</span><span class="pipe-arrow">→</span></div>
      <div class="pipe-step"><span class="pipe-label">HTTP</span><span class="pipe-arrow">→</span></div>
      <div class="pipe-step"><span class="pipe-label">Mapper</span><span class="pipe-arrow">→</span></div>
      <div class="pipe-step"><span class="pipe-label">Response DTO</span></div>
    </div>
    <div class="features-grid">${features}
    </div>
  </div>
</section>

<!-- MAKE:INTEGRATION -->
<section class="como">
  <div class="container">
    <p class="section-label">${t.makeLabel}</p>
    <h2 class="section-title">${t.makeH2}</h2>
    <p class="section-sub">${t.makeSub}</p>
    <div class="code-block" style="margin-bottom:1rem">
      <div class="code-body" style="padding:1rem 1.5rem; display:flex; gap:1.5rem; flex-wrap:wrap; align-items:flex-start;">
        <pre style="color:#7dd3fc; font-family:monospace; font-size:.82rem; white-space:pre">${t.makeCmd}</pre>
      </div>
    </div>
    <div style="background:var(--code-bg); border:1px solid var(--border); border-radius:6px; padding:1rem 1.5rem; overflow-x:auto;">
      ${t.makeFiles.map(f => `<div style="font-family:monospace;font-size:.78rem;color:#4a6680;line-height:1.9;white-space:nowrap">${f}</div>`).join('')}
    </div>
  </div>
</section>

<!-- EL CALL SITE -->
<section class="callsite">
  <div class="container">
    <p class="section-label">${t.callsiteLabel}</p>
    <h2 class="section-title">${t.callsiteH2}</h2>
    <p class="section-sub">${t.callsiteSub}</p>
    <div class="code-block">
      <div class="code-tabs">
        ${tabs}
      </div>
      <div class="code-panels">
        <div class="line-numbers" aria-hidden="true"></div>
        <div class="code-panel active" id="tab-noauth"><pre>${t.codeNoAuth}</pre></div>
        <div class="code-panel"        id="tab-path"><pre>${t.codePath}</pre></div>
        <div class="code-panel"        id="tab-body"><pre>${t.codeBody}</pre></div>
        <div class="code-panel"        id="tab-graphql"><pre>${t.codeGraphQL}</pre></div>
        <div class="code-panel"        id="tab-batch"><pre>${t.codeSendMany}</pre></div>
      </div>
    </div>
    <p style="margin-top:1rem;font-size:.82rem;color:#4a5568;">
      ${t.codeReadmeLink} <a href="https://github.com/CarlosGude/integrationEngine#readme" target="_blank" rel="noopener" style="color:var(--blue-light)">${t.codeReadmeLinkLabel} →</a>
    </p>
  </div>
</section>

<!-- DISEÑO EN CAPAS -->
<section class="capas">
  <div class="container">
    <p class="section-label">${t.capasLabel}</p>
    <h2 class="section-title">${t.capasH2}</h2>
    <p class="section-sub">${t.capasSub}</p>
    <table class="layers-table">
      <thead><tr>${layerHead}</tr></thead>
      <tbody>${layers}
      </tbody>
    </table>
    <p style="margin-top:1.5rem; color:#4a5568; font-size:.85rem;">${t.makeNote}</p>
  </div>
</section>

<!-- CTA -->
<section class="cta">
  <div class="container">
    <h2>${t.ctaH2}</h2>
    <p>${t.ctaP}</p>
    <div class="cta-buttons">
      <a href="https://github.com/CarlosGude/integrationEngine" class="btn btn-primary" target="_blank" rel="noopener">${t.ctaBtn1}</a>
      <a href="${lang === 'es' ? 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION_ES.md' : 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION.md'}" class="btn btn-ghost" target="_blank" rel="noopener">${t.ctaBtn2}</a>
      <a href="${t.ctaBtn3Href}" class="btn btn-ghost" target="_blank" rel="noopener">${t.ctaBtn3}</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <p>${t.footerTxt}</p>
</footer>

<script>${JS}<\/script>
</body>
</html>`;
}

/* ─────────────────────────────────────────────────────────────
   CSS
───────────────────────────────────────────────────────────── */
const CSS = `*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --navy:      #0d1b2e;
  --navy-mid:  #122040;
  --navy-light:#1c3057;
  --blue:      #2f6fbd;
  --blue-light:#4a8fd4;
  --red:       #c94f2c;
  --red-light: #e06840;
  --text:      #e8edf3;
  --muted:     #8fa3bd;
  --border:    #1e3352;
  --code-bg:   #0b1622;
  --white:     #ffffff;
}

body {
  font-family: "Roboto", sans-serif;
  background: var(--white);
  color: #1a2a4a;
  line-height: 1.6;
  padding-top: 48px;
}

/* ── TOPNAV ── */
.topnav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 200;
  height: 48px;
  background: var(--navy);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 1.5rem;
  gap: 1.5rem;
}
.topnav-brand {
  font-family: "Inter", sans-serif;
  font-weight: 800;
  font-size: .95rem;
  color: var(--white);
  text-decoration: none;
  white-space: nowrap;
  letter-spacing: -.01em;
  margin-right: auto;
}
.topnav-brand span { color: var(--blue-light); }
.topnav-links {
  display: flex;
  gap: 1.5rem;
}
.topnav-links a {
  font-size: .82rem;
  color: var(--muted);
  text-decoration: none;
  transition: color .2s;
  white-space: nowrap;
}
.topnav-links a:hover { color: var(--white); }
.lang-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
  font-size: .72rem;
}
.lang-opt {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 7px;
  border-radius: 4px;
  border: 1px solid transparent;
  text-decoration: none;
  cursor: pointer;
  transition: opacity .15s, border-color .15s;
}
.lp-flag { font-size: .88rem; }
.lp-string {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  background: #000;
  border-radius: 2px;
  padding: 1px 4px;
}
.lp-bracket { color: #3a5470; }
.lp-code    { font-weight: 700; letter-spacing: .06em; }
.lang-inactive { opacity: 1; }
.lang-inactive .lp-flag { filter: grayscale(20%); }
.lang-inactive .lp-code { color: var(--muted); }
.lang-inactive:hover { border-color: rgba(255,255,255,0.08); }
.lang-active { opacity: 1; border-color: rgba(255,255,255,0.15); }
.lang-active .lp-flag { filter: none; }
.lang-active .lp-code { color: #7dd3fc; }

@media (max-width: 500px) {
  .topnav-links { display: none; }
}

h1, h2, h3, .section-title, .hero h1, .cta h2 {
  font-family: "Inter", sans-serif;
}

.hero {
  position: relative;
  background: radial-gradient(ellipse 80% 60% at 50% -10%, #1a3a6e 0%, var(--navy) 70%);
  color: var(--text);
  text-align: center;
  padding: 4.5rem 1.5rem 3.5rem;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute;
  top: -80px; left: 50%;
  transform: translateX(-50%);
  width: 600px; height: 300px;
  background: radial-gradient(ellipse, rgba(47, 111, 189, 0.35) 0%, transparent 70%);
  pointer-events: none;
  filter: blur(20px);
}
.hero-badge {
  position: relative;
  font-size: .75rem;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: 2rem;
}
.hero h1 {
  position: relative;
  font-size: clamp(1.7rem, 5vw, 3.25rem);
  font-weight: 800;
  line-height: 1.2;
  color: var(--white);
  max-width: 760px;
  margin: 0 auto 1.25rem;
  text-shadow: 0 0 60px rgba(74, 143, 212, 0.4);
}
.hero p {
  position: relative;
  font-size: 1.1rem;
  color: var(--muted);
  max-width: 560px;
  margin: 0 auto 2rem;
}
.install-box {
  display: inline-block;
  max-width: calc(100% - 3rem);
  background: var(--code-bg);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: .75rem 1.5rem;
  font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
  font-size: .95rem;
  color: #7dd3fc;
  margin-bottom: 2.5rem;
  cursor: pointer;
  position: relative;
  overflow-x: auto;
  white-space: nowrap;
  transition: border-color .2s, box-shadow .2s;
}
.install-box:hover {
  border-color: var(--blue-light);
  box-shadow: 0 0 18px rgba(74, 143, 212, 0.25);
}
.install-box .copy-hint {
  position: absolute;
  top: -28px; left: 50%; transform: translateX(-50%);
  background: var(--blue);
  color: #fff;
  font-family: sans-serif;
  font-size: .72rem;
  padding: 2px 8px;
  border-radius: 4px;
  opacity: 0;
  pointer-events: none;
  transition: opacity .2s;
  white-space: nowrap;
}
.install-box.copied .copy-hint { opacity: 1; }

.hero-gh-btn {
  display: inline-block;
  background: var(--blue);
  color: #fff;
  font-family: "Inter", sans-serif;
  font-size: .9rem;
  font-weight: 700;
  padding: .6rem 1.5rem;
  border-radius: 6px;
  text-decoration: none;
  margin-bottom: 2rem;
  transition: opacity .2s, transform .1s;
}
.hero-gh-btn:hover { opacity: .85; transform: translateY(-1px); }

.hero-nav {
  position: relative;
  display: flex;
  justify-content: center;
  gap: 2rem;
  flex-wrap: wrap;
  align-items: center;
}
.hero-nav a {
  color: var(--blue-light);
  text-decoration: none;
  font-size: .95rem;
  transition: color .2s;
}
.hero-nav a:hover { color: var(--white); }

section { padding: 3.5rem 1.5rem; }
.container { max-width: 960px; margin: 0 auto; }

.section-label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .15em;
  text-transform: uppercase;
  color: var(--blue);
  margin-bottom: .75rem;
}
.section-title {
  font-size: clamp(1.4rem, 3.5vw, 2.2rem);
  font-weight: 800;
  color: var(--blue);
  margin-bottom: .75rem;
}
.section-sub {
  color: #4a5568;
  max-width: 640px;
  margin-bottom: 2.5rem;
}

.problema { background: #f8fafc; }
.problems-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1px;
  background: #dde3ec;
  border: 1px solid #dde3ec;
  border-radius: 8px;
  overflow: hidden;
}
.problem-card { background: #fff; padding: 1.5rem 1.25rem; }
.problem-card h3 { font-size: .95rem; font-weight: 700; color: var(--red); margin-bottom: .5rem; }
.problem-card p  { font-size: .82rem; color: #4a5568; line-height: 1.5; }

.como { background: var(--white); }
.pipeline {
  display: flex;
  align-items: center;
  overflow-x: auto;
  background: var(--navy);
  border-radius: 8px;
  margin-bottom: 2rem;
  padding: 1rem 1.25rem;
  -webkit-overflow-scrolling: touch;
  border: 1px solid var(--border);
}
.pipe-step { display: flex; align-items: center; white-space: nowrap; }
.pipe-label {
  font-family: "SFMono-Regular", Consolas, monospace;
  font-size: .78rem;
  padding: .45rem .85rem;
  border-radius: 5px;
  color: var(--muted);
  background: var(--navy-light);
  border: 1px solid transparent;
  transition: color .3s, border-color .3s, box-shadow .3s;
}
.pipe-label.pipe-active {
  color: #fff;
  border-color: var(--blue-light);
  box-shadow: 0 0 14px rgba(74, 143, 212, 0.45);
}
.pipe-label.highlight { background: var(--blue); color: #fff; font-weight: 700; }
.pipe-label.highlight.pipe-active {
  box-shadow: 0 0 20px rgba(47, 111, 189, 0.7);
  border-color: #7dd3fc;
}
.pipe-arrow { color: var(--border); padding: 0 .5rem; font-size: 1rem; transition: color .3s; }
.pipe-arrow.pipe-arrow-active { color: var(--blue-light); }

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1.5rem;
}
.feature-card { border: 1px solid #dde3ec; border-radius: 8px; padding: 1.5rem; }
.feature-card h3 { font-size: .95rem; font-weight: 700; color: var(--blue); margin-bottom: .5rem; }
.feature-card p  { font-size: .83rem; color: #4a5568; line-height: 1.55; }

.callsite { background: #f8fafc; }
.code-block {
  background: var(--code-bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
}
.code-tabs {
  display: flex;
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
}
.code-tab {
  padding: .6rem 1.25rem;
  font-size: .78rem;
  color: var(--muted);
  cursor: pointer;
  border: none;
  background: none;
  white-space: nowrap;
  transition: color .2s, background .2s;
}
.code-tab.active {
  color: #7dd3fc;
  background: var(--navy-mid);
  border-bottom: 2px solid var(--blue-light);
}
.code-tab:hover:not(.active) { color: var(--text); }
.code-panels { display: flex; padding: 1.25rem 0; }
.line-numbers {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  padding: 0 .75rem 0 1rem;
  min-width: 2.5rem;
  border-right: 1px solid var(--border);
  user-select: none;
  pointer-events: none;
}
.line-numbers span {
  font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
  font-size: .78rem;
  line-height: 1.7;
  color: #2e4a68;
}
.code-panel { display: none; flex: 1; padding: 0 1.25rem; min-width: 0; }
.code-panel.active { display: block; }
pre {
  font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
  font-size: .82rem;
  line-height: 1.7;
  white-space: pre;
  overflow-x: auto;
  color: #c9d8e8;
}
.kw  { color: var(--muted); }
.cls { color: #f87171; font-weight: 700; }
.met { color: #f87171; }
.str { color: #86efac; }
.cmt { color: #4a6680; font-style: italic; }
.var { color: #c9d8e8; }
.num { color: #fbbf24; }

.capas { background: var(--white); }
.layers-table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #dde3ec;
  font-size: .9rem;
}
.layers-table thead {
  background: var(--navy);
  color: var(--muted);
  font-size: .7rem;
  letter-spacing: .1em;
  text-transform: uppercase;
}
.layers-table th { padding: .75rem 1.25rem; text-align: left; }
.layers-table tbody tr:nth-child(odd)  { background: #fff; }
.layers-table tbody tr:nth-child(even) { background: #f0f5fb; }
.layers-table td { padding: 1rem 1.25rem; vertical-align: top; }
.layer-name  { font-family: monospace; font-weight: 700; color: var(--blue); font-size: .9rem; }
.layer-desc  { color: #4a5568; font-size: .85rem; line-height: 1.5; }
.layer-scope { color: var(--muted); font-size: .82rem; }

.cta {
  background: var(--navy);
  color: var(--text);
  text-align: center;
  padding: 4rem 1.5rem;
}
.cta h2 {
  font-size: clamp(1.4rem, 3vw, 2rem);
  font-weight: 800;
  color: var(--white);
  margin-bottom: .75rem;
}
.cta p { color: var(--muted); margin-bottom: 2rem; max-width: 480px; margin-left: auto; margin-right: auto; }
.cta-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.btn {
  display: inline-block;
  padding: .65rem 1.5rem;
  border-radius: 6px;
  font-size: .9rem;
  font-weight: 600;
  text-decoration: none;
  transition: opacity .2s, transform .1s;
}
.btn:hover { opacity: .85; transform: translateY(-1px); }
.btn-primary { background: var(--blue); color: #fff; }
.btn-ghost   { border: 1px solid var(--border); color: var(--muted); }

footer {
  background: var(--code-bg);
  color: var(--muted);
  text-align: center;
  padding: 1.5rem;
  font-size: .78rem;
}
footer a { color: var(--blue-light); text-decoration: none; }

@media (max-width: 600px) {
  section { padding: 2.5rem 1.25rem; }
  .hero { padding: 3rem 1.25rem 2.5rem; }
  .hero p { font-size: .95rem; }
  .install-box { font-size: .75rem; padding: .65rem 1rem; }
  .hero-gh-btn { display: block; text-align: center; }
  .problems-grid { grid-template-columns: 1fr; }
  .features-grid { grid-template-columns: 1fr; }
  .cta-buttons { flex-direction: column; align-items: stretch; }
  .cta-buttons .btn { text-align: center; }
  .hero-nav { gap: 1.25rem; }
  .layers-table thead { display: none; }
  .layers-table, .layers-table tbody, .layers-table tr, .layers-table td {
    display: block; width: 100%;
  }
  .layers-table td { padding: .75rem 1rem; }
  .line-numbers { display: none; }
}`;

/* ─────────────────────────────────────────────────────────────
   JS — verbatim de main.js
───────────────────────────────────────────────────────────── */
const JS = `
function copyInstall(el) {
  navigator.clipboard.writeText('composer require carlosgude/integration-engine').then(() => {
    el.classList.add('copied');
    setTimeout(() => el.classList.remove('copied'), 2000);
  });
}

function showTab(btn, panelId) {
  const block = btn.closest('.code-block');
  block.querySelectorAll('.code-tab').forEach(t => t.classList.remove('active'));
  block.querySelectorAll('.code-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(panelId).classList.add('active');
  renderLineNumbers(block);
}

function renderLineNumbers(block) {
  const activePanel = block.querySelector('.code-panel.active pre');
  if (!activePanel) return;
  const lines = activePanel.textContent.split('\\n');
  if (lines[lines.length - 1] === '') lines.pop();
  const gutter = block.querySelector('.line-numbers');
  if (!gutter) return;
  gutter.innerHTML = lines.map((_, i) => '<span>' + (i + 1) + '</span>').join('');
}

function initPipeline() {
  const labels = Array.from(document.querySelectorAll('.pipe-label'));
  const arrows = Array.from(document.querySelectorAll('.pipe-arrow'));
  const total  = labels.length;
  let current  = 0;
  function step() {
    labels.forEach(l => l.classList.remove('pipe-active'));
    arrows.forEach(a => a.classList.remove('pipe-arrow-active'));
    labels[current].classList.add('pipe-active');
    if (current > 0) arrows[current - 1].classList.add('pipe-arrow-active');
    current = (current + 1) % total;
  }
  step();
  setInterval(step, 650);
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.code-block').forEach(block => renderLineNumbers(block));
  initPipeline();
});`;
