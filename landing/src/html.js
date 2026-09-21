import { CSS } from './css.js';
import { JS }  from './client.js';
import en from './i18n/en.js';
import es from './i18n/es.js';

const translations = { en, es };

export function getHTML(lang = 'en') {
    const t = translations[lang] ?? translations.en;

    const benefitPills = t.heroBenefits.map(b => `<span class="benefit-pill">${b}</span>`).join('');

    const isEs = lang === 'es';
    const docsHref = isEs
        ? 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION_ES.md'
        : 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION.md';

    const compareLeft  = t.compareItems.map(i => `
      <div class="compare-item"><span class="ci-icon">&#10007;</span>${i.without}</div>`).join('');
    const compareRight = t.compareItems.map(i => `
      <div class="compare-item"><span class="ci-icon">&#10003;</span>${i.with}</div>`).join('');

    const bizCards = t.bizItems.map(i => `
      <div class="biz-card">
        <div class="biz-stat">${i.stat}</div>
        <p>${i.desc}</p>
      </div>`).join('');

    const extCards = t.extItems.map(i => `
      <div class="ext-card">
        <div class="ext-iface">${i.iface}</div>
        <p>${i.desc}</p>
      </div>`).join('');

    return String.raw`<!DOCTYPE html>
<html lang="${t.lang}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IntegrationEngine &mdash; Symfony API Integration Bundle</title>
<meta name="description" content="Build Stripe, SAP, Salesforce or any external API in Symfony without god classes. Automatic OAuth2, parallel requests and typed DTOs. One standard for every integration." />
<meta property="og:type"        content="website" />
<meta property="og:url"         content="https://integrationengine.dev/?lang=${lang}" />
<meta property="og:title"       content="IntegrationEngine &mdash; Symfony API Integration Bundle" />
<meta property="og:description" content="Build external API integrations in Symfony without god classes. Automatic OAuth2, parallel requests, typed DTOs." />
<meta name="twitter:card"        content="summary_large_image" />
<meta name="twitter:title"       content="IntegrationEngine &mdash; Symfony API Integration Bundle" />
<meta name="twitter:description" content="Build external API integrations in Symfony without god classes. Automatic OAuth2, parallel requests, typed DTOs." />
<link rel="canonical" href="https://integrationengine.dev/?lang=${lang}" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>${CSS}</style>
</head>
<body>

<!-- NAV -->
<nav class="topnav">
  <a class="topnav-brand" href="?lang=${lang}">Integration<span>Engine</span></a>
  <div class="topnav-links">
    <a href="#problem">${t.navProblem}</a>
    <a href="#example">${t.navExample}</a>
    <a href="#webhooks">${t.navWebhooks}</a>
    <a href="#pattern">${t.navPattern}</a>
  </div>
  <div class="topnav-actions">
    <a href="${docsHref}" target="_blank" rel="noopener" class="nav-btn">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
      Docs
    </a>
    <a href="https://github.com/CarlosGude/integrationEngine/wiki" target="_blank" rel="noopener" class="nav-btn">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>
      Wiki
    </a>
    <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener" class="nav-btn nav-btn-github">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0 1 12 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
      GitHub
    </a>
  </div>
  <div class="lang-pill">
    <a href="?lang=es" class="lang-opt ${isEs ? 'lang-active' : 'lang-inactive'}">
      <span class="lp-flag">&#127466;&#127480;</span>
      <span class="lp-string"><span class="lp-bracket">'</span><span class="lp-code">ES</span><span class="lp-bracket">'</span></span>
    </a>
    <a href="?lang=en" class="lang-opt ${isEs ? 'lang-inactive' : 'lang-active'}">
      <span class="lp-flag">&#127468;&#127463;</span>
      <span class="lp-string"><span class="lp-bracket">'</span><span class="lp-code">EN</span><span class="lp-bracket">'</span></span>
    </a>
  </div>
  <button class="nav-hamburger" onclick="toggleNav()" aria-label="Menu">&#9776;</button>
</nav>
<div class="nav-mobile-menu" id="nav-mobile-menu">
  <a href="#problem" onclick="toggleNav()">${t.navProblem}</a>
  <a href="#example" onclick="toggleNav()">${t.navExample}</a>
  <a href="#webhooks" onclick="toggleNav()">${t.navWebhooks}</a>
  <a href="#pattern" onclick="toggleNav()">${t.navPattern}</a>
  <hr class="nav-mobile-sep">
  <a href="${docsHref}" target="_blank" rel="noopener">Docs ↗</a>
  <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener">GitHub ↗</a>
</div>

<!-- HERO -->
<section class="hero">
  <div class="hero-badge">Symfony Bundle &middot; PHP 8.2+ &middot; Symfony 6.4+</div>
  <h1>${t.heroH1}</h1>
  <p>${t.heroP}</p>
  <div class="hero-benefits">${benefitPills}</div>
  <div class="install-box" onclick="copyInstall(this)">
    <span class="copy-hint">${t.copyHint}</span>
    composer require carlosgude/integration-engine
  </div>
  <div class="hero-actions">
    <a href="#pattern" class="btn-primary">${t.heroBtn1}</a>
    <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener noreferrer" class="btn-outline">${t.heroBtn2}</a>
  </div>
</section>

<!-- TRUST BAR -->
<div class="trust-bar">
  <span class="trust-item">
    <img src="https://img.shields.io/packagist/v/carlosgude/integration-engine?style=flat-square&labelColor=0d1b2e&color=2f6fbd&label=version" alt="Latest version" height="18" loading="lazy">
  </span>
  <span class="trust-sep">&middot;</span>
  <span class="trust-item trust-compat">PHP 8.2+ &middot; Symfony 6.4+</span>
</div>

<!-- THE PROBLEM -->
<section id="problem" class="s-light">
  <div class="container">
    <div class="eyebrow">${t.problemEyebrow}</div>
    <h2 class="s-heading">${t.problemH2}</h2>
    <p class="s-sub">${t.problemSub}</p>
    <div class="compare-wrap">
      <div class="compare-col bad-col">
        <div class="compare-col-header">&#10007;&ensp;${t.compareWithout}</div>
        <div class="compare-col-body">${compareLeft}
        </div>
      </div>
      <div class="compare-col good-col">
        <div class="compare-col-header">&#10003;&ensp;${t.compareWith}</div>
        <div class="compare-col-body">${compareRight}
        </div>
      </div>
    </div>
  </div>
</section>

<!-- BUSINESS VALUE -->
<section id="value" class="s-white biz-section">
  <div class="container">
    <div class="eyebrow">${t.bizEyebrow}</div>
    <h2 class="s-heading">${t.bizH2}</h2>
    <div class="biz-grid">${bizCards}
    </div>
  </div>
</section>

<!-- SOCIAL PROOF -->
<section id="proof" class="s-light proof-section">
  <div class="container">
    <div class="eyebrow">${t.proofEyebrow}</div>
    <h2 class="s-heading">${t.proofH2}</h2>
    <div class="proof-grid">
      ${t.proofItems.map(i => `
      <div class="proof-card">
        <div class="proof-stat">${i.stat}</div>
        <p>${i.desc}</p>
      </div>`).join('')}
    </div>
  </div>
</section>

<!-- PARALLEL EXECUTION -->
<section id="parallel" class="s-dark">
  <div class="container">
    <div class="eyebrow lt">${t.parallelEyebrow}</div>
    <h2 class="s-heading lt">${t.parallelH2}</h2>
    <p class="s-sub lt">${t.parallelSub}</p>
    <div class="parallel-timing">
      <div class="timing-block t-bad">
        <div class="timing-label">${t.parallelBefore}</div>
        <div class="timing-number">${t.parallelBeforeTime}</div>
        <div class="timing-detail">${t.parallelBeforeDetail}</div>
      </div>
      <div class="timing-block t-good">
        <div class="timing-label">${t.parallelAfter}</div>
        <div class="timing-number">${t.parallelAfterTime}</div>
        <div class="timing-detail">${t.parallelAfterDetail}</div>
      </div>
    </div>
    <div class="struct-panel">
      <div class="struct-header">PHP</div>
      <pre><span class="var">$requests</span> = [];
<span class="kw">foreach</span> (<span class="var">$stationIds</span> <span class="kw">as</span> <span class="var">$key</span> =&gt; <span class="var">$params</span>) {
    <span class="var">$requests</span>[<span class="var">$key</span>] = <span class="cls">EngineRequest</span>::<span class="fn">create</span>(
        <span class="key">actionName</span>: <span class="cls">GetStationByIdAction</span>::<span class="fn">getName</span>(),
        <span class="key">context</span>:    <span class="cls">DefaultActionContext</span>::<span class="fn">create</span>(<span class="var">$params</span>),
    );
}

<span class="cm">// All dispatched concurrently &mdash; total time &asymp; slowest request</span>
<span class="var">$results</span> = <span class="var">$this</span>-&gt;<span class="var">engine</span>-&gt;<span class="hl">sendManyOrFail</span>(<span class="var">$requests</span>);</pre>
    </div>
  </div>
</section>

<!-- GET STARTED -->
<section id="start" class="s-light">
  <div class="container">
    <div class="eyebrow">${t.startEyebrow}</div>
    <h2 class="s-heading">${t.startH2}</h2>
    <p class="s-sub start-sub">${t.startSub}</p>
    <div class="steps-grid">
      <div class="step">
        <div class="step-num">1</div>
        <h3>${t.startStep1Title}</h3>
        <div class="step-code">${t.startStep1Code}</div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <h3>${t.startStep2Title}</h3>
        <div class="step-code">${t.startStep2Code}</div>
        <div class="step-tree">${t.startStep2Tree}</div>
        <p>${t.startStep2Desc}</p>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <h3>${t.startStep3Title}</h3>
        <p>${t.startStep3Desc}</p>
        <a href="${docsHref}" target="_blank" rel="noopener">${t.startStep3Link}</a>
      </div>
    </div>
    <div class="struct-panel generator-panel">
      <div class="struct-header">MAKE:INTEGRATION OUTPUT</div>
      <pre><span class="cm">$ php bin/console make:integration MyApi GetUser</span>

MyApi/
<span class="key">├─</span> MyApi.yaml                    <span class="cm">${t.startGenYaml}</span>
<span class="key">└─</span> GetUser/
   <span class="key">├─</span> Request/<span class="hl">GetUserAction.php</span>    <span class="cm">${t.startGenAction}</span>
   <span class="key">└─</span> Response/
      <span class="key">├─</span> <span class="hl">GetUserResponse.php</span>       <span class="cm">${t.startGenResponse}</span>
      <span class="key">└─</span> <span class="hl">GetUserMapper.php</span>         <span class="cm">${t.startGenMapper}</span>

<span class="cm">${t.startGenIncrementalLabel}</span>
<span class="cm">$ php bin/console make:integration MyApi CreateOrder</span>
<span class="cm">${t.startGenIncrementalNote}</span></pre>
      <a href="${docsHref}" target="_blank" rel="noopener" class="btn-primary gen-docs-link">${t.startGenDocsLink}</a>
    </div>
  </div>
</section>

<!-- STRIPE EXAMPLE -->
<section id="example" class="s-white">
  <div class="container">
    <div class="eyebrow">${t.stripeEyebrow}</div>
    <h2 class="s-heading">${t.stripeH2}</h2>
    <p class="s-sub">${t.stripeSub}</p>
    <div class="example-panels">

      <!-- YAML -->
      <div class="struct-panel">
        <div class="struct-header">STRIPE.YAML</div>
        <pre><span class="key">GetToken</span>:
    <span class="val">action</span>: App\...\<span class="hl">GetTokenAction</span>
    <span class="val">method</span>: POST
    <span class="val">path</span>:   /v1/oauth/token

<span class="key">CreatePaymentIntent</span>:
    <span class="val">action</span>: App\...\<span class="hl">CreatePaymentIntentAction</span>
    <span class="val">method</span>: POST
    <span class="val">path</span>:   /v1/payment_intents
    <span class="val">authorization</span>:
        <span class="val">type</span>:         <span class="hl">dynamic</span>
        <span class="val">action</span>:       <span class="hl">GetToken</span>
        <span class="val">token_field</span>:  access_token
        <span class="val">ttl</span>:          3600</pre>
      </div>

      <!-- MAPPER -->
      <div class="example-code-panel">
        <div class="file-label">CreatePaymentIntentMapper.php</div>
        <div class="code-block"><span class="kw">final class</span> <span class="cls">CreatePaymentIntentMapper</span> <span class="kw">extends</span> <span class="cls">AbstractMapper</span>
{
    <span class="kw">public static function</span> <span class="fn">getAction</span>(): <span class="cls">string</span>
    {
        <span class="kw">return</span> <span class="cls">CreatePaymentIntentAction</span>::<span class="kw">class</span>;
    }

    <span class="kw">protected static function</span> <span class="fn">transform</span>(
        <span class="cls">AbstractAction</span> <span class="var">$a</span>, <span class="cls">array</span> <span class="var">$r</span>
    ): <span class="cls">ResponseInterface</span> {
        <span class="kw">return new</span> <span class="cls">CreatePaymentIntentResponse</span>(
            <span class="key">id</span>:     <span class="var">$r</span>[<span class="str">'id'</span>],
            <span class="key">secret</span>: <span class="var">$r</span>[<span class="str">'client_secret'</span>],
            <span class="key">status</span>: <span class="var">$r</span>[<span class="str">'status'</span>],
        );
    }
}</div>
      </div>

      <!-- USAGE (full width) -->
      <div class="example-code-panel example-panel-full">
        <div class="file-label">PaymentService.php &mdash; OAuth2 token is fetched, cached and refreshed automatically</div>
        <div class="code-block"><span class="var">$intent</span> = <span class="var">$this</span>-&gt;<span class="var">stripe</span>-&gt;<span class="fn">createPaymentIntent</span>(<span class="key">amount</span>: 2000, <span class="key">currency</span>: <span class="str">'eur'</span>);

<span class="fn">assert</span>(<span class="var">$intent</span> <span class="kw">instanceof</span> <span class="cls">CreatePaymentIntentResponse</span>);

<span class="kw">echo</span> <span class="var">$intent</span>-&gt;<span class="var">id</span>;     <span class="cm">// pi_3OqfK8LnFoNEqOv0abc123</span>
<span class="kw">echo</span> <span class="var">$intent</span>-&gt;<span class="var">secret</span>; <span class="cm">// pi_3OqfK8..._secret_XYZ</span>
<span class="kw">echo</span> <span class="var">$intent</span>-&gt;<span class="var">status</span>; <span class="cm">// requires_payment_method</span></div>
      </div>

    </div>
    <div class="example-cta">
      <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener" class="btn-primary">${t.stripeBtn}</a>
    </div>
  </div>
</section>

<!-- WEBHOOK SHOWCASE -->
<section id="webhooks" class="s-light">
  <div class="container">
    <div class="eyebrow">${t.webhookEyebrow}</div>
    <h2 class="s-heading">${t.webhookH2}</h2>
    <p class="s-sub">${t.webhookSub}</p>
    <div class="webhook-platforms" style="text-align: center; font-weight: 600; color: #2f6fbd; margin: 1.5rem 0; font-size: 14px;">${t.webhookPlatforms}</div>
    <div class="webhook-features feature-grid">
      ${t.webhookFeatures.map(f => `<div>✓ ${f}</div>`).join('')}
    </div>

    <div style="border-top: 1px solid #ddd; padding-top: 2rem; margin-bottom: 2rem;">
      <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 1.5rem;"><strong>Without IntegrationEngine</strong></div>
      <div class="example-panels">
        <!-- WEBHOOK ENTRY POINT: CONTROLLER -->
        <div class="example-code-panel">
          <div class="file-label">Step 1: Entry Point (manual verification &amp; storage)</div>
          <div class="code-block"><span class="cm">// WITHOUT ENGINE: Manual verification &amp; storage</span>
<span class="kw">public function</span> <span class="fn">webhook</span>(<span class="cls">Request</span> <span class="var">$req</span>): <span class="cls">Response</span>
{
    <span class="var">$body</span> = <span class="var">$req</span>-&gt;<span class="fn">getContent</span>();
    <span class="var">$sig</span> = <span class="var">$req</span>-&gt;headers-&gt;<span class="fn">get</span>(<span class="str">'X-Shopify-Hmac-SHA256'</span>);

    <span class="cm">// Verify signature (easy to get wrong)</span>
    <span class="kw">if</span> (!hash_equals(
        base64_encode(hash_hmac(<span class="str">'sha256'</span>, $body, $secret, <span class="kw">true</span>)),
        <span class="var">$sig</span>
    )) { <span class="kw">throw new</span> <span class="cls">BadRequestHttpException</span>; }

    <span class="cm">// Check duplicates manually (if you remember)</span>
    <span class="var">$fingerprint</span> = md5($body);
    <span class="kw">if</span> (<span class="var">$this</span>-&gt;<span class="var">cache</span>-&gt;<span class="fn">has</span>(<span class="var">$fingerprint</span>)) {
        <span class="kw">return new</span> <span class="cls">Response</span>(<span class="str">''</span>, 200); <span class="cm">// silent duplicate</span>
    }

    <span class="cm">// Parse &amp; store manually</span>
    <span class="var">$payload</span> = json_decode($body, <span class="kw">true</span>);
    <span class="var">$this</span>-&gt;<span class="var">db</span>-&gt;<span class="fn">insert</span>(<span class="str">'webhooks'</span>, <span class="var">$payload</span>);
    <span class="var">$this</span>-&gt;<span class="var">cache</span>-&gt;<span class="fn">set</span>(<span class="var">$fingerprint</span>, <span class="kw">true</span>, 86400);

    <span class="kw">return new</span> <span class="cls">Response</span>(<span class="str">''</span>, 202);
}</div>
        </div>

        <!-- WEBHOOK PROCESSING: WITHOUT FRAMEWORK -->
        <div class="example-code-panel">
          <div class="file-label">Step 2: Worker job (process from DB, manual parsing &amp; update)</div>
          <div class="code-block"><span class="cm">// Worker job or command: Get raw data from DB</span>
<span class="var">$webhook</span> = <span class="var">$this</span>-&gt;<span class="var">db</span>-&gt;<span class="fn">query</span>(<span class="str">'SELECT * FROM webhooks WHERE processed=0'</span>);

<span class="kw">foreach</span> (<span class="var">$webhook</span> <span class="kw">as</span> <span class="var">$row</span>) {
    <span class="cm">// Parse again (already parsed above, but here we go)</span>
    <span class="var">$payload</span> = json_decode(<span class="var">$row</span>[<span class="str">'body'</span>], <span class="kw">true</span>);

    <span class="cm">// Raw array accessed directly (bad)</span>
    <span class="var">$id</span> = <span class="var">$payload</span>[<span class="str">'id'</span>];
    <span class="var">$title</span> = <span class="var">$payload</span>[<span class="str">'title'</span>];
    <span class="var">$price</span> = (float) <span class="var">$payload</span>[<span class="str">'price'</span>];

    <span class="cm">// Do the actual update</span>
    <span class="var">$this</span>-&gt;<span class="var">inventory</span>-&gt;<span class="fn">syncProduct</span>(<span class="var">$id</span>, <span class="var">title</span>, <span class="var">$price</span>);

    <span class="var">$this</span>-&gt;<span class="var">db</span>-&gt;<span class="fn">update</span>(<span class="str">'webhooks'</span>, [<span class="str">'processed'</span> =&gt; 1]);
}</div>
        </div>
      </div>
    </div>

    <div style="border-top: 2px solid #2f6fbd; padding-top: 2rem;">
      <div style="font-size: 12px; color: #2f6fbd; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 1.5rem;"><strong>With IntegrationEngine</strong></div>

      <!-- Routing: Full width -->
      <div style="margin-bottom: 1.5rem;">
        <div class="example-code-panel">
          <div class="file-label">Step 1: Route it (Symfony&#39;s Webhook component &mdash; no controller)</div>
          <div class="code-block" style="min-height: 200px;"><span class="cm"># config/packages/framework.yaml</span>
<span class="key">framework</span>:
  <span class="key">webhook</span>:
    <span class="key">routing</span>:
      <span class="key">shopify</span>:                 <span class="cm"># POST /webhook/shopify</span>
        <span class="val">service</span>: App\Webhooks\Shopify\<span class="hl">ProductUpdatedParser</span>
        <span class="val">secret</span>: <span class="str">'%env(SHOPIFY_WEBHOOK_SECRET)%'</span></div>
        </div>
      </div>

      <!-- Parser + Listener: side by side, stacked on small screens -->
      <div class="panel-duo">
        <!-- WITH ENGINE: 2. Parser -->
        <div class="example-code-panel">
          <div class="file-label">Step 2: Parser (signature verified before anything is decoded)</div>
          <div class="code-block"><span class="kw">final class</span> <span class="cls">ProductUpdatedParser</span> <span class="kw">extends</span> <span class="cls">IntegrationWebhookRequestParser</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="attribute">#[Autowire(env: </span><span class="str">'SHOPIFY_WEBHOOK_SECRET'</span><span class="attribute">)]</span>
        <span class="kw">private</span> <span class="cls">string</span> <span class="var">$secret</span>,
    ) {}

    <span class="kw">public function</span> <span class="fn">getDefinition</span>(): <span class="cls">string</span>
    {
        <span class="kw">return</span> <span class="str">'products/update'</span>;
    }

    <span class="kw">public function</span> <span class="fn">getMapper</span>(): <span class="cls">AbstractWebhookMapper</span>
    {
        <span class="cm">// Payload in, typed DTO out. Nothing else.</span>
        <span class="kw">return new</span> <span class="cls">ProductUpdatedMapper</span>();
    }

    <span class="kw">protected function</span> <span class="fn">getSignatureVerifier</span>(): <span class="cls">SignatureVerifierInterface</span>
    {
        <span class="kw">return new</span> <span class="cls">ShopifyHmacSignatureVerifier</span>();
    }

    <span class="kw">protected function</span> <span class="fn">getSignatureSecret</span>(): <span class="cls">string</span>
    {
        <span class="kw">return</span> <span class="var">$this</span>-&gt;<span class="var">secret</span>;
    }
}</div>
        </div>

        <!-- WITH ENGINE: 3. Consumer + listener -->
        <div class="example-code-panel">
          <div class="file-label">Step 3: Listener (typed event, business logic in your domain)</div>
          <div class="code-block"><span class="attribute">#[AsRemoteEventConsumer(</span><span class="str">'shopify'</span><span class="attribute">)]</span>
<span class="kw">final readonly class</span> <span class="cls">ShopifyConsumer</span> <span class="kw">implements</span> <span class="cls">ConsumerInterface</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private</span> <span class="cls">WebhookEventDispatcher</span> <span class="var">$dispatcher</span>
    ) {}

    <span class="kw">public function</span> <span class="fn">consume</span>(<span class="cls">RemoteEvent</span> <span class="var">$event</span>): <span class="cls">void</span>
    {
        <span class="var">$this</span>-&gt;<span class="var">dispatcher</span>-&gt;<span class="fn">dispatch</span>(
            <span class="var">$event</span>, <span class="kw">new</span> <span class="cls">ProductUpdatedMapper</span>(), []
        );
    }
}

<span class="kw">final class</span> <span class="cls">ProductUpdatedListener</span>
{
    <span class="attribute">#[AsEventListener]</span>
    <span class="kw">public function</span> <span class="fn">__invoke</span>(<span class="cls">ProductUpdated</span> <span class="var">$event</span>): <span class="cls">void</span>
    {
        <span class="var">$this</span>-&gt;<span class="var">inventory</span>-&gt;<span class="fn">syncProduct</span>(
            <span class="var">$event</span>-&gt;<span class="var">productId</span>,
            <span class="var">$event</span>-&gt;<span class="var">title</span>,
            <span class="var">$event</span>-&gt;<span class="var">price</span>
        );
    }
}</div>
        </div>
      </div>
    </div>
    <div class="example-cta">
      <a href="https://github.com/CarlosGude/integrationEngine/blob/main/WEBHOOK.md" target="_blank" rel="noopener" class="btn-primary">${t.webhookBtn}</a>
    </div>
  </div>
</section>

<!-- OBSERVABILITY -->
<section id="observability" class="s-white">
  <div class="container">
    <div class="eyebrow">${t.obsEyebrow}</div>
    <h2 class="s-heading">${t.obsH2}</h2>
    <p class="s-sub">${t.obsSub}</p>
    <div class="obs-features feature-grid">
      ${t.obsFeatures.map(f => `<div>✓ ${f}</div>`).join('')}
    </div>
    <div class="panel-stack">

      <!-- Step 1: Generate -->
      <div class="example-code-panel">
        <div class="file-label">Step 1: One command generates everything</div>
        <div class="code-block"><span class="cm">$ php bin/console make:observability shopify</span>
<span class="cm"></span>
<span class="cm">Generates:</span>
<span class="str">src/Integration/Shopify/ShopifyObservabilitySetup.php</span>
<span class="cm">Updated: config/services.yaml</span></div>
      </div>

      <!-- Step 2: The generated class -->
      <div class="example-panel-full">
        <div class="example-code-panel">
          <div class="file-label">Step 2: Generated class with 3 observability layers</div>
          <div class="code-block"><span class="kw">class</span> <span class="cls">ShopifyObservabilitySetup</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private</span> <span class="cls">LoggerInterface</span> <span class="var">$logger</span>,
        <span class="cm">// private PrometheusRegistry $prometheus,</span>
        <span class="cm">// private SlackNotifier $slack,</span>
    ) {}

    <span class="kw">public function</span> <span class="fn">register</span>(<span class="cls">LifecycleEventDispatcher</span> <span class="var">$dispatcher</span>): <span class="kw">void</span>
    {
        <span class="cm">// Layer 1: Auto-logging (ActionStarted, ActionCompleted, ActionFailed)</span>
        <span class="cls">ObservabilitySetup</span>::<span class="fn">register</span>(<span class="var">$dispatcher</span>, <span class="var">$this</span>-&gt;<span class="var">logger</span>, [
            <span class="str">'logging'</span> =&gt; <span class="kw">true</span>,
            <span class="str">'slow_request_threshold_ms'</span> =&gt; 3000,
            <span class="str">'integration_filter'</span> =&gt; <span class="str">'shopify'</span>,
        ]);

        <span class="cm">// Layer 2: Metrics (ActionCompleted + ActionFailed)</span>
        <span class="cls">ObservabilitySetup</span>::<span class="fn">register</span>(<span class="var">$dispatcher</span>, <span class="var">$this</span>-&gt;<span class="var">logger</span>, [
            <span class="str">'metrics_callback'</span> =&gt; <span class="fn">fn</span>(<span class="var">$e</span>) =&gt; <span class="var">$this</span>-&gt;<span class="fn">recordMetrics</span>(<span class="var">$e</span>),
            <span class="str">'integration_filter'</span> =&gt; <span class="str">'shopify'</span>,
        ]);

        <span class="cm">// Layer 3: Errors (ActionFailed)</span>
        <span class="cls">ObservabilitySetup</span>::<span class="fn">register</span>(<span class="var">$dispatcher</span>, <span class="var">$this</span>-&gt;<span class="var">logger</span>, [
            <span class="str">'error_callback'</span> =&gt; <span class="fn">fn</span>(<span class="var">$e</span>) =&gt; <span class="var">$this</span>-&gt;<span class="fn">recordError</span>(<span class="var">$e</span>),
            <span class="str">'integration_filter'</span> =&gt; <span class="str">'shopify'</span>,
        ]);
    }

    <span class="cm">// …and two stubs it writes for you, one per callback:</span>
    <span class="cm">// recordMetrics() with a commented Prometheus histogram,</span>
    <span class="cm">// recordError() with a commented Sentry captureException().</span>
}</div>
        </div>
      </div>

      <!-- Step 3: Wiring -->
      <div class="example-code-panel">
        <div class="file-label">Step 3: Auto-wired in services.yaml</div>
        <div class="code-block"><span class="key">app.shopify.observability</span>:
    <span class="key">class</span>: <span class="val">App\Integration\Shopify\ShopifyObservabilitySetup</span>
    <span class="key">calls</span>:
      - [<span class="fn">register</span>, [<span class="str">'@IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher'</span>, <span class="str">'@logger'</span>]]</div>
      </div>

      <!-- Step 4: Optional scaling -->
      <div class="example-panel-full">
        <div class="example-code-panel">
          <div class="file-label">Step 4 (optional): Extract to handlers for clarity</div>
          <div class="code-block"><span class="cm">// As your observability grows, split into handlers</span>
<span class="kw">class</span> <span class="cls">ShopifyObservabilitySetup</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private</span> <span class="cls">LoggerInterface</span> <span class="var">$logger</span>,
        <span class="kw">private</span> <span class="cls">MetricsHandler</span> <span class="var">$metrics</span>,
        <span class="kw">private</span> <span class="cls">ErrorHandler</span> <span class="var">$errors</span>,
    ) {}

    <span class="kw">public function</span> <span class="fn">register</span>(<span class="cls">LifecycleEventDispatcher</span> <span class="var">$dispatcher</span>): <span class="kw">void</span>
    {
        <span class="cls">ObservabilitySetup</span>::<span class="fn">register</span>(<span class="var">$dispatcher</span>, <span class="var">$this</span>-&gt;<span class="var">logger</span>, [
            <span class="str">'metrics_callback'</span> =&gt; <span class="var">$this</span>-&gt;<span class="var">metrics</span>-&gt;<span class="fn">record</span>(...),
            <span class="str">'error_callback'</span> =&gt; <span class="var">$this</span>-&gt;<span class="var">errors</span>-&gt;<span class="fn">handle</span>(...),
        ]);
    }
}</div>
        </div>
      </div>

      <!-- Bonus: Timing breakdown -->
      <div class="example-panel-full">
        <div class="example-code-panel">
          <div class="file-label">Bonus: Track HTTP vs. mapping separately</div>
          <div class="code-block"><span class="cm">// Listen to ResponseMapped to see the breakdown</span>
<span class="attr">#[AsEventListener(event: ResponseMapped::class)]</span>
<span class="kw">public function</span> <span class="fn">onResponseMapped</span>(<span class="cls">ResponseMapped</span> <span class="var">$event</span>): <span class="kw">void</span>
{
    <span class="cm">// See where time was actually spent</span>
    <span class="var">$httpTime</span> = <span class="var">$event</span>-&gt;<span class="fn">httpDurationMs</span>();
    <span class="var">$mappingTime</span> = <span class="var">$event</span>-&gt;<span class="fn">mappingDurationMs</span>();
    <span class="var">$overhead</span> = <span class="var">$event</span>-&gt;<span class="fn">totalDurationMs</span>() - <span class="var">$httpTime</span> - <span class="var">$mappingTime</span>;

    <span class="var">$this</span>-&gt;<span class="var">metrics</span>-&gt;<span class="fn">gauge</span>(<span class="str">'shopify.http_ms'</span>, <span class="var">$httpTime</span>);
    <span class="var">$this</span>-&gt;<span class="var">metrics</span>-&gt;<span class="fn">gauge</span>(<span class="str">'shopify.mapping_ms'</span>, <span class="var">$mappingTime</span>);
    <span class="var">$this</span>-&gt;<span class="var">metrics</span>-&gt;<span class="fn">gauge</span>(<span class="str">'shopify.overhead_ms'</span>, <span class="var">$overhead</span>);
}</div>
        </div>
      </div>

    </div>
    <div class="example-cta">
      <a href="https://github.com/CarlosGude/integrationEngine/blob/main/OBSERVABILITY.md" target="_blank" rel="noopener" class="btn-primary">${t.obsBtn}</a>
    </div>
  </div>
</section>

<!-- EXTENSION POINTS -->
<section id="extend" class="s-dark ext-section">
  <div class="container">
    <div class="eyebrow lt">${t.extEyebrow}</div>
    <h2 class="s-heading lt">${t.extH2}</h2>
    <p class="s-sub lt">${t.extSub}</p>
    <div class="ext-grid">${extCards}
    </div>
  </div>
</section>

<!-- MID-PAGE CTA -->
<div class="mid-cta">
  <div class="container">
    <p>${t.midCtaText}</p>
    <div class="mid-cta-actions">
      <a href="${docsHref}" target="_blank" rel="noopener" class="btn-primary"><span class="cta-icon">&#128214;</span> ${t.midCtaBtn}</a>
      <a href="#pattern" onclick="event.preventDefault();openAndScrollToPattern()" class="btn-outline mid-cta-outline"><span class="cta-icon">&#8595;</span> ${t.midCtaAlt}</a>
    </div>
  </div>
</div>

<!-- PROJECT STATUS -->
<section id="status" class="s-light">
  <div class="container">
    <div class="eyebrow">${t.statusEyebrow}</div>
    <h2 class="s-heading">${t.statusH2}</h2>
    <p class="s-sub">${t.statusP}</p>
    <div class="status-grid">
      <div class="status-card">
        <p>${t.statusNow}</p>
      </div>
      <div class="status-card">
        <p>${t.statusNext}</p>
      </div>
      <div class="status-card">
        <p>${t.statusLater}</p>
      </div>
    </div>
    <div class="status-footer">
      <a href="https://github.com/CarlosGude/integrationEngine/blob/main/ROADMAP.md" target="_blank" rel="noopener" class="btn-outline">
        ${t.statusRoadmapLink}
      </a>
    </div>
  </div>
</section>

<!-- THE PATTERN -->
<section id="pattern" class="s-white">
  <div class="container">
    <div class="eyebrow">${t.patternEyebrow}</div>
    <h2 class="s-heading">${t.patternH2}</h2>
    <p class="s-sub">${t.patternSub}</p>
    <button class="pattern-expand-btn" id="pattern-expand-btn"
      onclick="togglePattern()"
      data-label-open="${t.patternExpandLabel}"
      data-label-close="${t.patternCollapseLabel}">
      <span id="peb-label">${t.patternExpandLabel}</span>
      <span class="peb-chevron">&#8964;</span>
    </button>

    <div class="patron-collapsible" id="patron-collapsible">
    <div class="patron-grid">

      <!-- 1. CONFIGURATION -->
      <div>
        <div class="patron-header">
          <div class="patron-num">1</div>
          <div class="patron-meta">
            <h3>${t.p1Title}</h3>
            <div class="anti">${t.p1Anti}</div>
            <div class="sol">${t.p1Sol}</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.withoutPattern}</div>
            <div class="file-label">src/Traditional/RailwayApiService.php</div>
            <div class="code-block"><span class="kw">namespace</span> App\Traditional;

<span class="kw">use</span> <span class="cls">Symfony\Contracts\HttpClient\HttpClientInterface</span>;

<span class="kw">class</span> <span class="cls">RailwayApiService</span>
{
    <span class="cm">${t.p1CmBase}</span>
    <span class="kw">private const</span> <span class="key">BASE</span> = <span class="bad-hl"><span class="str">'https://api.railway-stations.org'</span></span>;

    <span class="kw">public function</span> <span class="fn">fetchStats</span>(): <span class="cls">array</span>
    {
        <span class="var">$r</span> = <span class="var">$this</span>-&gt;<span class="var">http</span>-&gt;<span class="fn">request</span>(<span class="bad-hl"><span class="str">'GET'</span>, <span class="str">self::BASE . '/stats'</span></span>);
        <span class="kw">return</span> <span class="var">$r</span>-&gt;<span class="fn">toArray</span>();
    }

    <span class="kw">public function</span> <span class="fn">fetchStations</span>(<span class="cls">string</span> <span class="var">$countryCode</span>): <span class="cls">array</span>
    {
        <span class="var">$raw</span> = <span class="var">$this</span>-&gt;<span class="var">http</span>
            -&gt;<span class="fn">request</span>(<span class="bad-hl"><span class="str">'GET'</span>,
                <span class="str">self::BASE . '/photoStationsByCountry/' . </span><span class="var">$countryCode</span></span>)
            -&gt;<span class="fn">toArray</span>();
        <span class="var">$base</span>     = <span class="var">$raw</span>[<span class="str">'photoBaseUrl'</span>];
        <span class="var">$stations</span> = [];
        <span class="kw">foreach</span> (<span class="var">$raw</span>[<span class="str">'stations'</span>] <span class="kw">as</span> <span class="var">$s</span>) {
            <span class="var">$s</span>[<span class="str">'_photoBase'</span>] = <span class="var">$base</span>;
            <span class="var">$s</span>[<span class="str">'_hasPhoto'</span>]  = <span class="fn">isset</span>(<span class="var">$s</span>[<span class="str">'photos'</span>][0]);
            <span class="var">$s</span>[<span class="str">'_photoUrl'</span>]  = <span class="fn">isset</span>(<span class="var">$s</span>[<span class="str">'photos'</span>][0])
                ? <span class="var">$base</span> . <span class="var">$s</span>[<span class="str">'photos'</span>][0][<span class="str">'path'</span>]
                : <span class="kw">null</span>;
            <span class="var">$stations</span>[] = <span class="var">$s</span>;
        }
        <span class="kw">return</span> <span class="var">$stations</span>;
    }

    <span class="kw">public function</span> <span class="fn">fetchStation</span>(<span class="cls">string</span> <span class="var">$cc</span>, <span class="cls">string</span> <span class="var">$id</span>): ?<span class="cls">array</span>
    {
        <span class="var">$raw</span> = <span class="var">$this</span>-&gt;<span class="var">http</span>
            -&gt;<span class="fn">request</span>(<span class="bad-hl"><span class="str">'GET'</span>,
                <span class="str">self::BASE . '/photoStationById/'
                    . </span><span class="var">$cc</span> <span class="str">. '/' . </span><span class="var">$id</span></span>)
            -&gt;<span class="fn">toArray</span>();
        <span class="kw">return</span> <span class="var">$raw</span>[<span class="str">'stations'</span>][0] ?? <span class="kw">null</span>;
    }
}</div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.enginePattern}</div>
            <div class="file-label">src/Engine/Infrastructure/Integrations/RailwayStations/RailwayStations.yaml</div>
            <div class="code-block"><span class="good-hl"><span class="key">GetStats</span>:
    <span class="key">action</span>: <span class="str">App\...\GetStatsAction</span>
    <span class="key">method</span>: <span class="str">GET</span>
    <span class="key">path</span>:   <span class="str">/stats</span></span>

<span class="good-hl"><span class="key">GetStationsByCountry</span>:
    <span class="key">action</span>: <span class="str">App\...\GetStationsByCountryAction</span>
    <span class="key">method</span>: <span class="str">GET</span>
    <span class="key">path</span>:   <span class="str">/photoStationsByCountry/{country}</span></span>

<span class="good-hl"><span class="key">GetStationById</span>:
    <span class="key">action</span>: <span class="str">App\...\GetStationByIdAction</span>
    <span class="key">method</span>: <span class="str">GET</span>
    <span class="key">path</span>:   <span class="str">/photoStationById/{country}/{stationId}</span></span></div>
            <div class="file-label">config/packages/integration_engine.yaml</div>
            <div class="code-block"><span class="key">integration_engine</span>:
    <span class="key">integrations</span>:
        <span class="key">railway_stations</span>:
            <span class="good-hl"><span class="key">base_url</span>: <span class="str">'https://api.railway-stations.org'</span></span>
            <span class="key">config_path</span>: <span class="str">'%kernel.project_dir%/src/Engine/
                         Infrastructure/Integrations/
                         RailwayStations/RailwayStations.yaml'</span></div>
          </div>
        </div>
        <div class="insight">${t.p1Insight}</div>
      </div>

      <!-- 2. ROUTES -->
      <div>
        <div class="patron-header">
          <div class="patron-num">2</div>
          <div class="patron-meta">
            <h3>${t.p2Title}</h3>
            <div class="anti">${t.p2Anti}</div>
            <div class="sol">${t.p2Sol}</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.withoutPattern}</div>
            <div class="file-label">src/Traditional/RailwayApiService.php</div>
            <div class="code-block"><span class="cm">${t.p2CmOneParam}</span>
<span class="kw">public function</span> <span class="fn">fetchStations</span>(<span class="cls">string</span> <span class="var">$countryCode</span>): <span class="cls">array</span>
{
    <span class="var">$raw</span> = <span class="var">$this</span>-&gt;<span class="var">http</span>-&gt;<span class="fn">request</span>(
        <span class="str">'GET'</span>,
        <span class="bad-hl"><span class="str">self::BASE . '/photoStationsByCountry/' . </span><span class="var">$countryCode</span></span>
    )-&gt;<span class="fn">toArray</span>();
}

<span class="cm">${t.p2CmTwoParam}</span>
<span class="kw">public function</span> <span class="fn">fetchStation</span>(<span class="cls">string</span> <span class="var">$countryCode</span>, <span class="cls">string</span> <span class="var">$stationId</span>): ?<span class="cls">array</span>
{
    <span class="var">$raw</span> = <span class="var">$this</span>-&gt;<span class="var">http</span>-&gt;<span class="fn">request</span>(
        <span class="str">'GET'</span>,
        <span class="bad-hl"><span class="str">self::BASE
            . '/photoStationById/'
            . </span><span class="var">$countryCode</span>
            <span class="str">. '/'</span>
            . <span class="var">$stationId</span></span>
    )-&gt;<span class="fn">toArray</span>();
}

<span class="cm">${t.p2CmNull}</span></div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.enginePattern}</div>
            <div class="file-label">src/Engine/Infrastructure/Integrations/RailwayStations/RailwayStationsIntegration.php</div>
            <div class="code-block"><span class="kw">public function</span> <span class="fn">getStationById</span>(<span class="cls">string</span> <span class="var">$country</span>, <span class="cls">string</span> <span class="var">$stationId</span>): <span class="cls">GetStationByIdResponse</span>
{
    <span class="var">$response</span> = <span class="var">$this</span>-&gt;<span class="var">engine</span>-&gt;<span class="fn">send</span>(
        <span class="key">actionName</span>: <span class="cls">GetStationByIdAction</span>::<span class="fn">getName</span>(),
        <span class="key">context</span>:    <span class="good-hl"><span class="cls">DefaultActionContext</span>::<span class="fn">create</span>([
            <span class="str">'country'</span>   =&gt; <span class="var">$country</span>,
            <span class="str">'stationId'</span> =&gt; <span class="var">$stationId</span>,
        ])</span>,
    );
    \assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetStationByIdResponse</span>);
    <span class="kw">return</span> <span class="var">$response</span>;
}

<span class="cm">${t.p2CmMissing}</span></div>
          </div>
        </div>
        <div class="insight">${t.p2Insight}</div>
      </div>

      <!-- 3. MAPPING -->
      <div>
        <div class="patron-header">
          <div class="patron-num">3</div>
          <div class="patron-meta">
            <h3>${t.p3Title}</h3>
            <div class="anti">${t.p3Anti}</div>
            <div class="sol">${t.p3Sol}</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.withoutPattern}</div>
            <div class="file-label">src/Traditional/Controller/GetStationsByCountryController.php</div>
            <div class="code-block"><span class="kw">foreach</span> (<span class="var">$stations</span> <span class="kw">as</span> <span class="var">$s</span>) {
    <span class="var">$result</span>[] = [
        <span class="str">'id'</span>        =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="str">'id'</span></span>],
        <span class="str">'title'</span>     =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="str">'title'</span></span>],       <span class="cm">${t.p3CmRawField}</span>
        <span class="str">'lat'</span>       =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="str">'lat'</span></span>],
        <span class="str">'lon'</span>       =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="str">'lon'</span></span>],         <span class="cm">${t.p3CmNotLng}</span>
        <span class="str">'has_photo'</span> =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="key">'_hasPhoto'</span></span>],  <span class="cm">${t.p3CmPrivate}</span>
        <span class="str">'photo_url'</span> =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="key">'_photoUrl'</span></span>],  <span class="cm">${t.p3CmPrivate}</span>
    ];
}
<span class="cm">${t.p3CmRenameEvery}</span></div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.enginePattern}</div>
            <div class="file-label">src/Engine/.../GetStationsByCountryMapper.php</div>
            <div class="code-block"><span class="kw">final class</span> <span class="cls">GetStationsByCountryMapper</span> <span class="kw">extends</span> <span class="cls">AbstractMapper</span>
{
    <span class="kw">protected static function</span> <span class="fn">transform</span>(
        <span class="cls">AbstractAction</span> <span class="var">$action</span>, <span class="cls">array</span> <span class="var">$response</span>
    ): <span class="cls">ResponseInterface</span> {
        <span class="var">$photoBaseUrl</span> = <span class="var">$response</span>[<span class="good-hl"><span class="str">'photoBaseUrl'</span></span>];  <span class="cm">${t.p3CmOnlyPlace}</span>
        <span class="var">$stations</span> = <span class="fn">array_map</span>(
            <span class="kw">fn</span>(<span class="cls">array</span> <span class="var">$s</span>) =&gt; <span class="cls">StationDto</span>::<span class="fn">fromApiData</span>(<span class="var">$s</span>, <span class="var">$photoBaseUrl</span>),
            <span class="var">$response</span>[<span class="good-hl"><span class="str">'stations'</span></span>],              <span class="cm">${t.p3CmOnlyPlace}</span>
        );
        <span class="kw">return new</span> <span class="cls">GetStationsByCountryResponse</span>(<span class="var">$stations</span>);
    }
}</div>
            <div class="file-label">src/Engine/.../StationDto.php</div>
            <div class="code-block"><span class="kw">public static function</span> <span class="fn">fromApiData</span>(<span class="cls">array</span> <span class="var">$station</span>, <span class="cls">string</span> <span class="var">$photoBaseUrl</span>): <span class="cls">self</span>
{
    <span class="var">$firstPhoto</span> = <span class="var">$station</span>[<span class="good-hl"><span class="str">'photos'</span></span>][0] ?? <span class="kw">null</span>;  <span class="cm">${t.p3CmOnlyPlace}</span>
    <span class="kw">return new</span> <span class="cls">self</span>(
        <span class="key">id</span>:       <span class="var">$station</span>[<span class="good-hl"><span class="str">'id'</span></span>],
        <span class="key">title</span>:    <span class="var">$station</span>[<span class="good-hl"><span class="str">'title'</span></span>],
        <span class="key">lat</span>:      (<span class="cls">float</span>) <span class="var">$station</span>[<span class="good-hl"><span class="str">'lat'</span></span>],
        <span class="key">lon</span>:      (<span class="cls">float</span>) <span class="var">$station</span>[<span class="good-hl"><span class="str">'lon'</span></span>],
        <span class="key">hasPhoto</span>: <span class="var">$firstPhoto</span> !== <span class="kw">null</span>,
        <span class="key">photoUrl</span>: <span class="var">$firstPhoto</span> !== <span class="kw">null</span>
            ? <span class="var">$photoBaseUrl</span> . <span class="var">$firstPhoto</span>[<span class="good-hl"><span class="str">'path'</span></span>]
            : <span class="kw">null</span>,
    );
}
<span class="cm">${t.p3CmRenameOne}</span></div>
          </div>
        </div>
        <div class="insight">${t.p3Insight}</div>
      </div>

      <!-- 4. ACL -->
      <div>
        <div class="patron-header">
          <div class="patron-num">4</div>
          <div class="patron-meta">
            <h3>${t.p4Title}</h3>
            <div class="anti">${t.p4Anti}</div>
            <div class="sol">${t.p4Sol}</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.withoutPattern}</div>
            <div class="file-label">src/Traditional/Controller/GetStationsByCountryController.php</div>
            <div class="code-block"><span class="kw">namespace</span> App\Traditional\Controller;

<span class="bad-hl"><span class="kw">use</span> <span class="cls">App\Traditional\RailwayApiService</span>;</span>

<span class="kw">class</span> <span class="cls">GetStationsByCountryController</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(<span class="kw">private</span> <span class="bad-hl"><span class="cls">RailwayApiService</span></span> <span class="var">$api</span>) {}

    #[<span class="cls">Route</span>(<span class="str">'/traditional/stations/{country}'</span>)]
    <span class="kw">public function</span> <span class="fn">__invoke</span>(<span class="cls">string</span> <span class="var">$country</span>): <span class="cls">JsonResponse</span>
    {
        <span class="var">$stations</span> = <span class="var">$this</span>-&gt;<span class="var">api</span>-&gt;<span class="fn">fetchStations</span>(<span class="var">$country</span>);
        <span class="cm">${t.p4CmMapsConv}</span>
        <span class="kw">return new</span> <span class="cls">JsonResponse</span>(<span class="var">$result</span>);
    }
}
<span class="cm">${t.p4CmSwitchBad}</span></div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.enginePattern}</div>
            <div class="file-label">src/Engine/Controller/GetStationsByCountryController.php</div>
            <div class="code-block"><span class="kw">namespace</span> App\Engine\Controller;

<span class="good-hl"><span class="kw">use</span> <span class="cls">App\Engine\Application\StationService</span>;</span>

<span class="kw">final class</span> <span class="cls">GetStationsByCountryController</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(<span class="kw">private readonly</span> <span class="good-hl"><span class="cls">StationService</span></span> <span class="var">$service</span>) {}

    #[<span class="cls">Route</span>(<span class="str">'/engine/stations/{country}'</span>)]
    <span class="kw">public function</span> <span class="fn">__invoke</span>(<span class="cls">string</span> <span class="var">$country</span>): <span class="cls">JsonResponse</span>
    {
        <span class="var">$stations</span> = <span class="var">$this</span>-&gt;<span class="var">service</span>-&gt;<span class="fn">getStationsByCountry</span>(<span class="var">$country</span>);
        <span class="kw">return new</span> <span class="cls">JsonResponse</span>(
            <span class="fn">array_map</span>(<span class="kw">fn</span>(<span class="cls">Station</span> <span class="var">$s</span>) =&gt; <span class="var">$s</span>-&gt;<span class="fn">toArray</span>(), <span class="var">$stations</span>)
        );
    }
}
<span class="cm">${t.p4CmSwitchGood}</span></div>
            <div class="file-label">src/Engine/Application/StationService.php</div>
            <div class="code-block"><span class="kw">final class</span> <span class="cls">StationService</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private readonly</span> <span class="cls">RailwayStationsIntegration</span> <span class="var">$integration</span>,
    ) {}

    <span class="kw">public function</span> <span class="fn">getStationsByCountry</span>(<span class="cls">string</span> <span class="var">$country</span>): <span class="cls">array</span>
    {
        <span class="var">$response</span> = <span class="var">$this</span>-&gt;<span class="var">integration</span>-&gt;<span class="fn">getStationsByCountry</span>(<span class="var">$country</span>);
        <span class="kw">return</span> <span class="fn">array_map</span>(
            <span class="kw">fn</span>(<span class="cls">StationDto</span> <span class="var">$dto</span>) =&gt; <span class="var">$this</span>-&gt;<span class="fn">toDomain</span>(<span class="var">$dto</span>),
            <span class="var">$response</span>-&gt;<span class="var">stations</span>,
        );
    }

    <span class="kw">private function</span> <span class="fn">toDomain</span>(<span class="cls">StationDto</span> <span class="var">$dto</span>): <span class="cls">Station</span>
    {
        <span class="kw">return new</span> <span class="cls">Station</span>(
            <span class="key">id</span>: <span class="var">$dto</span>-&gt;<span class="var">id</span>, <span class="key">title</span>: <span class="var">$dto</span>-&gt;<span class="var">title</span>,
            <span class="key">lat</span>: <span class="var">$dto</span>-&gt;<span class="var">lat</span>, <span class="key">lon</span>: <span class="var">$dto</span>-&gt;<span class="var">lon</span>,
            <span class="key">hasPhoto</span>: <span class="var">$dto</span>-&gt;<span class="var">hasPhoto</span>, <span class="key">photoUrl</span>: <span class="var">$dto</span>-&gt;<span class="var">photoUrl</span>,
        );
    }
}</div>
          </div>
        </div>
        <div class="insight">${t.p4Insight}</div>
      </div>

      <!-- 5. BATCH -->
      <div>
        <div class="patron-header">
          <div class="patron-num">5</div>
          <div class="patron-meta">
            <h3>${t.p5Title}</h3>
            <div class="anti">${t.p5Anti}</div>
            <div class="sol">${t.p5Sol}</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.withoutPattern}</div>
            <div class="file-label">src/Traditional/Controller/GetStationsBatchController.php</div>
            <div class="code-block"><span class="kw">foreach</span> (<span class="var">$pairs</span> <span class="kw">as</span> <span class="var">$pair</span>) {
    [<span class="var">$country</span>, <span class="var">$stationId</span>] = <span class="fn">explode</span>(<span class="str">'/'</span>, <span class="var">$pair</span>, 2) + [<span class="str">''</span>, <span class="str">''</span>];

    <span class="bad-hl"><span class="cm">${t.p5CmBlocked}</span>
    <span class="var">$s</span> = <span class="var">$this</span>-&gt;<span class="var">api</span>-&gt;<span class="fn">fetchStation</span>(<span class="var">$country</span>, <span class="var">$stationId</span>);</span>

    <span class="var">$result</span>[<span class="var">$pair</span>] = [<span class="str">'title'</span> =&gt; <span class="var">$s</span>[<span class="str">'title'</span>], <span class="str">'lat'</span> =&gt; <span class="var">$s</span>[<span class="str">'lat'</span>]];
}
<span class="cm">${t.p5CmBatchBad}</span></div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>${t.enginePattern}</div>
            <div class="file-label">src/Engine/.../RailwayStationsIntegration.php</div>
            <div class="code-block"><span class="kw">public function</span> <span class="fn">getManyStationsById</span>(<span class="cls">array</span> <span class="var">$stations</span>): <span class="cls">array</span>
{
    <span class="var">$requests</span> = [];
    <span class="kw">foreach</span> (<span class="var">$stations</span> <span class="kw">as</span> <span class="var">$key</span> =&gt; <span class="var">$params</span>) {
        <span class="var">$requests</span>[<span class="var">$key</span>] = <span class="cls">EngineRequest</span>::<span class="fn">create</span>(
            <span class="key">actionName</span>: <span class="cls">GetStationByIdAction</span>::<span class="fn">getName</span>(),
            <span class="key">context</span>:    <span class="cls">DefaultActionContext</span>::<span class="fn">create</span>([
                <span class="str">'country'</span>   =&gt; <span class="var">$params</span>[<span class="str">'country'</span>],
                <span class="str">'stationId'</span> =&gt; <span class="var">$params</span>[<span class="str">'stationId'</span>],
            ]),
        );
    }

    <span class="good-hl"><span class="cm">${t.p5CmAllSame}</span>
    <span class="kw">return</span> <span class="var">$this</span>-&gt;<span class="var">engine</span>-&gt;<span class="fn">sendManyOrFail</span>(<span class="var">$requests</span>);</span>
}
<span class="cm">${t.p5CmBatchGood}</span></div>
          </div>
        </div>
        <div class="insight">${t.p5Insight}</div>
      </div>

    </div>
    </div>
  </div>
</section>

<!-- WIKI & DOCUMENTATION -->
<section id="wiki" class="s-white">
  <div class="container">
    <div class="eyebrow">${t.wikiEyebrow}</div>
    <h2 class="s-heading">${t.wikiH2}</h2>
    <p class="s-sub">${t.wikiSub}</p>
    <div class="wiki-features feature-grid">
      ${t.wikiFeatures.map(f => `<a href="${f.href}" target="_blank" rel="noopener" style="text-decoration: none; color: inherit; display: block;">📚 <strong>${f.title}</strong><br/><small>${f.desc}</small></a>`).join('')}
    </div>
    <div style="margin-top: 2.5rem; text-align: center;">
      <a href="https://github.com/CarlosGude/integrationEngine/wiki" target="_blank" rel="noopener" class="btn-primary">${t.wikiBtn}</a>
    </div>
  </div>
</section>

<!-- THANKS -->
<section id="thanks" class="s-white thanks-section">
  <div class="container">
    <div class="eyebrow">${t.thanksEyebrow}</div>
    <h2 class="s-heading">${t.thanksH2}</h2>
    <p class="thanks-p">${t.thanksP}</p>
  </div>
</section>

<!-- CTA -->
<section id="contact" class="s-dark cta-section">
  <div class="container cta-inner">
    <div class="eyebrow lt">${t.ctaEyebrow}</div>
    <h2 class="s-heading lt">${t.ctaH2}</h2>
    <p class="s-sub lt">${t.ctaSub}</p>
    <div class="cta-actions">
      <a href="${t.ctaEmailHref}" class="btn-primary cta-btn">
        <span class="cta-icon">&#9993;</span> ${t.ctaEmailLabel}
        <span class="cta-email-addr">${t.ctaEmail}</span>
      </a>
      <a href="https://github.com/CarlosGude/integrationEngine/discussions" target="_blank" rel="noopener" class="btn-outline cta-btn">
        <span class="cta-icon">&#128172;</span> ${t.ctaDiscuss}
      </a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <span class="footer-brand">Integration<span>Engine</span></span>
    <p>
      <a href="https://integrationengine.dev" target="_blank" rel="noopener noreferrer">integrationengine.dev</a>
      &nbsp;&middot;&nbsp;
      <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener noreferrer">GitHub</a>
      &nbsp;&middot;&nbsp;
      <a href="https://github.com/CarlosGude/integrationEngine/wiki" target="_blank" rel="noopener noreferrer">Wiki</a>
      &nbsp;&middot;&nbsp;
      <a href="https://packagist.org/packages/carlosgude/integration-engine" target="_blank" rel="noopener noreferrer">Packagist</a>
    </p>
  </div>
</footer>

<script>${JS}</script>
</body>
</html>`;
}
