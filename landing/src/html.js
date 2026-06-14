import { CSS } from './css.js';
import { JS }  from './client.js';
import es from './i18n/es.js';
import en from './i18n/en.js';

const T = { es, en };

export function getHTML(lang) {
    const t = T[lang];

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

    const docsHref = lang === 'es'
        ? 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION_ES.md'
        : 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION.md';

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
    <a href="#problema">${t.navLabels[0]}</a>
    <a href="#como">${t.navLabels[1]}</a>
    <a href="#callsite">${t.navLabels[2]}</a>
    <span class="topnav-sep"></span>
    <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener">GitHub</a>
    <a href="${docsHref}" target="_blank" rel="noopener">${lang === 'es' ? 'Documentación' : 'Documentation'}</a>
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

<!-- EL PROBLEMA -->
<section id="problema" class="problema">
  <div class="container">
    <p class="section-label">${t.problemaLabel}</p>
    <h2 class="section-title">${t.problemaH2}</h2>
    <p class="section-sub">${t.problemaSub}</p>
    <div class="problems-grid">${problems}
    </div>
  </div>
</section>

<!-- POR QUÉ NO HTTPCLIENT -->
<section class="problema" style="padding-top:0">
  <div class="container">
    <p class="section-label">${t.whyLabel}</p>
    <h2 class="section-title">${t.whyH2}</h2>
    <p class="section-sub">${t.whySub}</p>
  </div>
</section>

<!-- COMO FUNCIONA -->
<section id="como" class="como">
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
<section id="callsite" class="callsite">
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

<!-- CTA -->
<section class="cta">
  <div class="container">
    <h2>${t.ctaH2}</h2>
    <p>${t.ctaP}</p>
    <div class="cta-buttons">
      <a href="https://github.com/CarlosGude/integrationEngine" class="btn btn-primary" target="_blank" rel="noopener">${t.ctaBtn1}</a>
      <a href="${docsHref}" class="btn btn-ghost" target="_blank" rel="noopener">${t.ctaBtn2}</a>
      <a href="${t.ctaBtn3Href}" class="btn btn-ghost" target="_blank" rel="noopener">${t.ctaBtn3}</a>
    </div>
    <p style="margin-top:2rem;font-size:.82rem;color:var(--muted);">
      ${t.feedbackLabel} <a href="https://github.com/CarlosGude/integrationEngine/discussions" target="_blank" rel="noopener" style="color:var(--blue-light)">${t.feedbackLink}</a>
      &nbsp;·&nbsp;
      ${t.contactLabel} <a href="mailto:${t.contactLink}" style="color:var(--blue-light)">${t.contactLink}</a>
    </p>
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
