export default {
    async fetch(request, env, ctx) {
        const url  = new URL(request.url);
        const lang = url.searchParams.get('lang') === 'en' ? 'en' : 'es';
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
        otherLang: 'en', otherFlag: '🇬🇧', otherLabel: 'English',
        heroBadge: 'Symfony Bundle · PHP 8.2+ · Packagist',
        heroH1:    'Deja de escribir el mismo cliente HTTP una y otra vez',
        heroP:     'Un motor de integración para Symfony que centraliza tus APIs externas bajo contratos claros.',
        heroNav: [
            { label: 'GitHub',        href: 'https://github.com/CarlosGude/integrationEngine' },
            { label: 'Packagist',     href: 'https://packagist.org/packages/carlosgude/integration-engine' },
            { label: 'Documentación', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION_ES.md' },
            { label: 'Demo',          href: 'https://github.com/CarlosGude/integrationEngine-use-example' },
        ],
        copyHint: '¡Copiado!',
        problemaLabel: 'El Problema',
        problemaH2:    'Cada integración acaba siendo un caso aislado',
        problemaSub:   'Diferentes formatos, autenticaciones inconsistentes, lógica de cache duplicada. El código se fragmenta y cada nueva API es empezar de cero.',
        problems: [
            { h3: 'Auth duplicada',    p: 'Tokens y cache reimplementados en cada integración.' },
            { h3: 'Sin contrato común', p: 'Cada cliente HTTP tiene su propia estructura.' },
            { h3: 'Difícil de testear', p: 'HTTP acoplado al dominio. Imposible aislar.' },
            { h3: 'Sin consistencia',   p: 'Cada desarrollador resuelve el problema a su manera.' },
            { h3: 'Cero visibilidad',   p: 'Sin trazabilidad, sin logs unificados, sin contexto compartido.' },
        ],
        comoLabel: 'Cómo Funciona',
        comoH2:    'Un flujo único para todas tus integraciones',
        comoSub:   'Un único punto de entrada. Cada paso tiene una responsabilidad clara.',
        features: [
            { h3: 'Auth dinámica con cache',  p: 'OAuth, sesiones, API keys. El engine los resuelve y cachea automáticamente.' },
            { h3: 'Context de path',          p: '<code>/orders/{id}</code> se resuelve en tiempo de llamada. Fallo explícito si falta parámetro.' },
            { h3: 'Headers en tres capas',    p: 'YAML → auth → capa de llamada. Cada capa sobreescribe a la anterior. Sin magia.' },
            { h3: 'Respuestas tipadas',       p: 'Cada acción define su propio Response DTO con contrato garantizado.' },
            { h3: 'Totalmente extensible',    p: 'Client, cache y config source sustituibles con una línea en YAML.' },
            { h3: 'Scaffolding incluido',     p: '<code>make:integration</code> genera Mapper, Response DTO y YAML en segundos.' },
        ],
        callsiteLabel: 'El Call Site',
        callsiteH2:    'Una línea. Siempre la misma.',
        callsiteSub:   'Sin strings mágicos — todo a través de contratos.',
        tabs: [
            { label: 'Sin auth',           id: 'tab-noauth' },
            { label: 'Con path params',    id: 'tab-path' },
            { label: 'Con body y headers', id: 'tab-body' },
        ],
        codeNoAuth: `<span class="cmt">// Sin autenticación</span>\n<span class="var">$registry</span><span class="kw">-&gt;</span><span class="met">get</span>(\n    <span class="cls">AcmeIntegration</span><span class="kw">::</span><span class="cls">NAME</span>\n)<span class="kw">-&gt;</span><span class="met">send</span>(\n    <span class="cls">GetUsersAction</span><span class="kw">::</span><span class="met">getName</span>()\n);`,
        codePath:   `<span class="cmt">// Con parámetros de ruta — /users/{id}</span>\n<span class="var">$registry</span><span class="kw">-&gt;</span><span class="met">get</span>(\n    <span class="cls">AcmeIntegration</span><span class="kw">::</span><span class="cls">NAME</span>\n)<span class="kw">-&gt;</span><span class="met">send</span>(\n    <span class="cls">GetUserAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    context: <span class="cls">DefaultActionContext</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="num">42</span>])\n);`,
        codeBody:   `<span class="cmt">// Con body y headers personalizados</span>\n<span class="var">$registry</span><span class="kw">-&gt;</span><span class="met">get</span>(\n    <span class="cls">AcmeIntegration</span><span class="kw">::</span><span class="cls">NAME</span>\n)<span class="kw">-&gt;</span><span class="met">send</span>(\n    <span class="cls">CreateOrderAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    body:    <span class="var">$body</span>,\n    headers: <span class="kw">new</span> <span class="cls">CorrelationHeaders</span>(<span class="var">$id</span>)\n);`,
        capasLabel: 'Diseño en Capas',
        capasH2:    'El bundle propone. No impone.',
        capasSub:   'Tres niveles que emergen solos. Usa los que necesites.',
        layersHead: ['Clase', 'Responsabilidad', 'Alcance'],
        layers: [
            { name: 'CreateChargeAction', desc: 'Solo declara el método, el path y el DTO de respuesta. Sin lógica HTTP.',                 scope: 'Acción concreta' },
            { name: 'StripeAction',       desc: 'Auth, path base y headers comunes de Stripe. Reutilizado por todas sus acciones.',        scope: 'Integración' },
            { name: 'AbstractAction',     desc: 'Contrato base que provee el engine. Extensible sin tocar el core.',                      scope: 'Bundle' },
        ],
        makeNote: 'El comando <code>make:integration</code> crea el config, las clases y el YAML en un solo paso.',
        ctaH2:   'Empieza en un comando',
        ctaP:    'Sin boilerplate. Sin decisiones arbitrarias. Solo tu lógica de negocio.',
        ctaBtn1: 'Ver en GitHub',
        ctaBtn2: 'Documentación',
        footerTxt: 'IntegrationEngine &mdash; <a href="https://github.com/CarlosGude/integrationEngine">CarlosGude/integrationEngine</a> &mdash; MIT License',
    },

    en: {
        htmlLang: 'en',
        pageTitle: 'IntegrationEngine — Symfony Bundle',
        otherLang: 'es', otherFlag: '🇪🇸', otherLabel: 'Español',
        heroBadge: 'Symfony Bundle · PHP 8.2+ · Packagist',
        heroH1:    'Stop writing the same HTTP client over and over again',
        heroP:     'An integration engine for Symfony that centralises your external APIs under clear contracts.',
        heroNav: [
            { label: 'GitHub',        href: 'https://github.com/CarlosGude/integrationEngine' },
            { label: 'Packagist',     href: 'https://packagist.org/packages/carlosgude/integration-engine' },
            { label: 'Documentation', href: 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION.md' },
            { label: 'Demo',          href: 'https://github.com/CarlosGude/integrationEngine-use-example' },
        ],
        copyHint: 'Copied!',
        problemaLabel: 'The Problem',
        problemaH2:    'Every integration ends up as an isolated case',
        problemaSub:   'Different formats, inconsistent authentication, duplicated cache logic. The codebase fragments and every new API means starting from scratch.',
        problems: [
            { h3: 'Duplicated auth',    p: 'Tokens and cache reimplemented in every integration.' },
            { h3: 'No shared contract', p: 'Every HTTP client has its own structure.' },
            { h3: 'Hard to test',       p: 'HTTP coupled to the domain. Impossible to isolate.' },
            { h3: 'No consistency',     p: 'Every developer solves the problem their own way.' },
            { h3: 'Zero visibility',    p: 'No traceability, no unified logs, no shared context.' },
        ],
        comoLabel: 'How It Works',
        comoH2:    'A single flow for all your integrations',
        comoSub:   'A single entry point. Every step has a clear responsibility.',
        features: [
            { h3: 'Dynamic auth with cache',  p: 'OAuth, sessions, API keys. The engine resolves and caches them automatically.' },
            { h3: 'Path context',             p: '<code>/orders/{id}</code> is resolved at call time. Explicit failure if a parameter is missing.' },
            { h3: 'Headers in three layers',  p: 'YAML → auth → call layer. Each layer overrides the previous. No magic.' },
            { h3: 'Typed responses',          p: 'Every action defines its own Response DTO with a guaranteed contract.' },
            { h3: 'Fully extensible',         p: 'Client, cache and config source replaceable with one line in YAML.' },
            { h3: 'Scaffolding included',     p: '<code>make:integration</code> generates Mapper, Response DTO and YAML in seconds.' },
        ],
        callsiteLabel: 'The Call Site',
        callsiteH2:    'One line. Always the same.',
        callsiteSub:   'No magic strings — everything through contracts.',
        tabs: [
            { label: 'No auth',          id: 'tab-noauth' },
            { label: 'Path params',      id: 'tab-path' },
            { label: 'Body and headers', id: 'tab-body' },
        ],
        codeNoAuth: `<span class="cmt">// No authentication</span>\n<span class="var">$registry</span><span class="kw">-&gt;</span><span class="met">get</span>(\n    <span class="cls">AcmeIntegration</span><span class="kw">::</span><span class="cls">NAME</span>\n)<span class="kw">-&gt;</span><span class="met">send</span>(\n    <span class="cls">GetUsersAction</span><span class="kw">::</span><span class="met">getName</span>()\n);`,
        codePath:   `<span class="cmt">// With path parameters — /users/{id}</span>\n<span class="var">$registry</span><span class="kw">-&gt;</span><span class="met">get</span>(\n    <span class="cls">AcmeIntegration</span><span class="kw">::</span><span class="cls">NAME</span>\n)<span class="kw">-&gt;</span><span class="met">send</span>(\n    <span class="cls">GetUserAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    context: <span class="cls">DefaultActionContext</span><span class="kw">::</span><span class="met">create</span>([<span class="str">'id'</span> <span class="kw">=&gt;</span> <span class="num">42</span>])\n);`,
        codeBody:   `<span class="cmt">// With body and custom headers</span>\n<span class="var">$registry</span><span class="kw">-&gt;</span><span class="met">get</span>(\n    <span class="cls">AcmeIntegration</span><span class="kw">::</span><span class="cls">NAME</span>\n)<span class="kw">-&gt;</span><span class="met">send</span>(\n    <span class="cls">CreateOrderAction</span><span class="kw">::</span><span class="met">getName</span>(),\n    body:    <span class="var">$body</span>,\n    headers: <span class="kw">new</span> <span class="cls">CorrelationHeaders</span>(<span class="var">$id</span>)\n);`,
        capasLabel: 'Layered Design',
        capasH2:    'The bundle proposes. It does not impose.',
        capasSub:   'Three levels that emerge naturally. Use whichever you need.',
        layersHead: ['Class', 'Responsibility', 'Scope'],
        layers: [
            { name: 'CreateChargeAction', desc: 'Only declares the method, path and response DTO. No HTTP logic.',                    scope: 'Concrete action' },
            { name: 'StripeAction',       desc: 'Auth, base path and common Stripe headers. Reused by all Stripe actions.',           scope: 'Integration' },
            { name: 'AbstractAction',     desc: 'Base contract provided by the engine. Extensible without touching the core.',        scope: 'Bundle' },
        ],
        makeNote: 'The <code>make:integration</code> command creates the config, classes and YAML in a single step.',
        ctaH2:   'Get started in one command',
        ctaP:    'No boilerplate. No arbitrary decisions. Just your business logic.',
        ctaBtn1: 'View on GitHub',
        ctaBtn2: 'Documentation',
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
  <div class="topnav-flags">
    <a href="?lang=es" class="${lang === 'es' ? 'flag-active' : ''}" title="Español">🇪🇸</a>
    <a href="?lang=en" class="${lang === 'en' ? 'flag-active' : ''}" title="English">🇬🇧</a>
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
  <nav class="hero-nav">
    ${navLinks}
  </nav>
</header>

<!-- EL PROBLEMA -->
<section class="problema">
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
      </div>
    </div>
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
      <a href="https://github.com/CarlosGude/integration-engine" class="btn btn-primary" target="_blank" rel="noopener">${t.ctaBtn1}</a>
      <a href="https://github.com/CarlosGude/integration-engine/blob/main/DOCUMENTATION.md" class="btn btn-ghost" target="_blank" rel="noopener">${t.ctaBtn2}</a>
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
   CSS — verbatim de styles.css + .lang-toggle añadido
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
.topnav-flags {
  display: flex;
  gap: .5rem;
  align-items: center;
  border-left: 1px solid var(--border);
  padding-left: 1rem;
}
.topnav-flags a {
  font-size: 1.15rem;
  text-decoration: none;
  opacity: .45;
  transition: opacity .2s, transform .15s;
  line-height: 1;
}
.topnav-flags a:hover { opacity: 1; transform: scale(1.15); }
.topnav-flags a.flag-active { opacity: 1; }

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
  font-size: clamp(2rem, 5vw, 3.25rem);
  font-weight: 800;
  line-height: 1.15;
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

section { padding: 5rem 1.5rem; }
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
  font-size: clamp(1.6rem, 3.5vw, 2.2rem);
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
.code-panel { display: none; flex: 1; padding: 0 1.25rem; }
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
  font-size: clamp(1.5rem, 3vw, 2rem);
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