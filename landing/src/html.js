import { CSS } from './css.js';
import { JS }  from './client.js';

export function getHTML() {
    return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IntegrationEngine &mdash; Demo</title>
<meta name="description" content="One standard for every external API in your Symfony projects. Ship faster. Onboard instantly. Stop doing archaeology." />
<meta property="og:type"        content="website" />
<meta property="og:url"         content="https://integrationengine.dev/" />
<meta property="og:title"       content="IntegrationEngine &mdash; Demo" />
<meta property="og:description" content="One standard for every external API in your Symfony projects." />
<meta property="og:site_name"   content="IntegrationEngine" />
<meta name="twitter:card"        content="summary" />
<meta name="twitter:title"       content="IntegrationEngine &mdash; Demo" />
<meta name="twitter:description" content="One standard for every external API in your Symfony projects." />
<link rel="canonical" href="https://integrationengine.dev/" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>${CSS}</style>
</head>
<body>

<!-- NAV -->
<nav class="topnav">
  <a class="topnav-brand" href="#">Integration<span>Engine</span></a>
  <div class="topnav-links">
    <a href="#problem">Problem</a>
    <a href="#demo">Demo</a>
    <a href="#pattern">The Pattern</a>
    <span class="topnav-sep"></span>
    <a href="https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION.md" target="_blank" rel="noopener">Docs</a>
    <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener">GitHub</a>
  </div>
  <div class="lang-pill">
    <a href="?lang=es" class="lang-opt lang-inactive">
      <span class="lp-flag">🇪🇸</span>
      <span class="lp-string"><span class="lp-bracket">'</span><span class="lp-code">ES</span><span class="lp-bracket">'</span></span>
    </a>
    <a href="?lang=en" class="lang-opt lang-active">
      <span class="lp-flag">🇬🇧</span>
      <span class="lp-string"><span class="lp-bracket">'</span><span class="lp-code">EN</span><span class="lp-bracket">'</span></span>
    </a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-badge">Symfony Bundle &middot; PHP 8.2+ &middot; Symfony 7+</div>
  <h1>Your external integrations<br>deserve a pattern</h1>
  <p>Without structure, every API ends up with its own shape, its own logic, its own technical debt. After a few months you don&rsquo;t have integrations. You have a zoo.</p>
  <div class="install-box" onclick="copyInstall(this)">
    <span class="copy-hint">Copied!</span>
    composer require carlosgude/integration-engine
  </div>
  <div class="hero-actions">
    <a href="#demo" class="btn-primary">See live demo</a>
    <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" class="btn-outline">Source code</a>
  </div>
</section>

<!-- THE PROBLEM -->
<section id="problem" class="s-light">
  <div class="container">
    <div class="eyebrow">The Problem</div>
    <h2 class="s-heading">Why integrations degenerate</h2>
    <p class="s-sub">Without a pattern, entropy wins. Every integration brings its own conventions and the code becomes archaeology.</p>
    <div class="problems-grid">
      <div class="problem-card">
        <div class="problem-icon">&#127381;</div>
        <h3>Inevitable god class</h3>
        <p>Without a defined entry point, HTTP methods, parsing and logic pile up in a single class. Impossible to test, impossible to scale.</p>
      </div>
      <div class="problem-card">
        <div class="problem-icon">&#128279;</div>
        <h3>Implicit contracts</h3>
        <p>Responses travel as <code>array&lt;string, mixed&gt;</code>. Every layer that touches them has to know the exact field names of the API.</p>
      </div>
      <div class="problem-card">
        <div class="problem-icon">&#9203;</div>
        <h3>Sequential batch</h3>
        <p>The <code>foreach</code> blocks: each request waits for the previous one. 10 items = 10&times; the time of one. It doesn&rsquo;t scale, and nothing warns you.</p>
      </div>
    </div>
  </div>
</section>

<!-- THE SOLUTION -->
<section id="structure" class="s-dark">
  <div class="container">
    <div class="eyebrow lt">The Solution</div>
    <h2 class="s-heading lt">One predictable structure for every integration</h2>
    <p class="s-sub lt">If you know one integration, you know them all. The engine enforces the same shape across every API you integrate.</p>
    <div class="struct-split">
      <div class="struct-panel">
        <div class="struct-header">ACTION MAP (YAML)</div>
        <pre><span class="cm"># RailwayStations.yaml &mdash; contract visible at a glance</span>

<span class="key">GetStats</span>:
    <span class="val">action</span>: App\\...\\<span class="hl">GetStatsAction</span>
    <span class="val">method</span>: GET
    <span class="val">path</span>:   /stats

<span class="key">GetStationsByCountry</span>:
    <span class="val">action</span>: App\\...\\<span class="hl">GetStationsByCountryAction</span>
    <span class="val">method</span>: GET
    <span class="val">path</span>:   /photoStationsByCountry/<span class="ph">{country}</span>

<span class="key">GetStationById</span>:
    <span class="val">action</span>: App\\...\\<span class="hl">GetStationByIdAction</span>
    <span class="val">method</span>: GET
    <span class="val">path</span>:   /photoStationById/<span class="ph">{country}</span>/<span class="ph">{stationId}</span></pre>
      </div>
      <div class="struct-panel">
        <div class="struct-header">TYPICAL DIRECTORY</div>
        <pre>src/Infrastructure/Integrations/<span class="hl">RailwayStations</span>/
&boxvr;&boxh;&boxh; <span class="hl">RailwayStationsIntegration.php</span>  <span class="cm">&larr; facade</span>
&boxvr;&boxh;&boxh; <span class="hl">RailwayStations.yaml</span>             <span class="cm">&larr; action map</span>
&boxvr;&boxh;&boxh; GetStats/
&boxv;   &boxvr;&boxh;&boxh; Request/<span class="key">GetStatsAction.php</span>
&boxv;   &boxur;&boxh;&boxh; Response/
&boxv;       &boxvr;&boxh;&boxh; <span class="key">GetStatsResponse.php</span>
&boxv;       &boxur;&boxh;&boxh; <span class="key">GetStatsMapper.php</span>
&boxur;&boxh;&boxh; GetStationById/
    &boxvr;&boxh;&boxh; Request/<span class="key">GetStationByIdAction.php</span>
    &boxur;&boxh;&boxh; Response/
        &boxvr;&boxh;&boxh; <span class="key">GetStationByIdResponse.php</span>
        &boxur;&boxh;&boxh; <span class="key">GetStationByIdMapper.php</span></pre>
      </div>
    </div>
  </div>
</section>

<!-- LIVE DEMO -->
<section id="demo" class="s-dark" style="padding-top:0;border-top:1px solid var(--border)">
  <div class="container" style="padding-top:3.5rem">
    <div class="eyebrow lt">Live Demo</div>
    <h2 class="s-heading lt">The same endpoints. Two implementations.</h2>
    <p class="s-sub lt">3 stations in batch. Run both versions and observe the difference in code and response time.</p>

    <div class="demo-split">
      <div class="demo-panel bad">
        <div class="demo-head"><span class="demo-dot"></span>Without pattern &mdash; sequential <code>foreach</code></div>
        <div class="demo-code"><span class="kw">foreach</span> (<span class="var">$pairs</span> <span class="kw">as</span> <span class="var">$pair</span>) {
    [<span class="var">$cc</span>, <span class="var">$id</span>] = <span class="fn">explode</span>(<span class="str">'/'</span>, <span class="var">$pair</span>);

    <span class="bad-hl"><span class="cm">// blocks &mdash; others wait here</span>
    <span class="var">$s</span> = <span class="var">$this</span>-&gt;<span class="var">api</span>-&gt;<span class="fn">fetchStation</span>(<span class="var">$cc</span>, <span class="var">$id</span>);</span>

    <span class="var">$result</span>[<span class="var">$pair</span>] = [<span class="str">'title'</span> =&gt; <span class="var">$s</span>[<span class="str">'title'</span>]];
}
<span class="cm">// 3 stations &times; 250ms = ~750ms</span></div>
        <div class="demo-output" id="trad-output"><div class="out-idle">Waiting for execution&hellip;</div></div>
        <div class="demo-timer">
          <span class="timer-label">Time:</span>
          <span class="timer-val idle" id="trad-timer">&mdash;</span>
        </div>
      </div>

      <div class="demo-panel good">
        <div class="demo-head"><span class="demo-dot"></span>Engine pattern &mdash; <code>sendManyOrFail()</code></div>
        <div class="demo-code"><span class="kw">foreach</span> (<span class="var">$stations</span> <span class="kw">as</span> <span class="var">$key</span> =&gt; <span class="var">$params</span>) {
    <span class="var">$requests</span>[<span class="var">$key</span>] = <span class="cls">EngineRequest</span>::<span class="fn">create</span>(
        <span class="key">actionName</span>: <span class="cls">GetStationByIdAction</span>::<span class="fn">getName</span>(),
        <span class="key">context</span>:    <span class="cls">DefaultActionContext</span>::<span class="fn">create</span>(<span class="var">$params</span>),
    );
}

<span class="good-hl"><span class="cm">// all go out at the same time</span>
<span class="kw">return</span> <span class="var">$this</span>-&gt;<span class="var">engine</span>-&gt;<span class="fn">sendManyOrFail</span>(<span class="var">$requests</span>);</span>
<span class="cm">// 3 stations &rarr; ~250ms (the slowest)</span></div>
        <div class="demo-output" id="eng-output"><div class="out-idle">Waiting for execution&hellip;</div></div>
        <div class="demo-timer">
          <span class="timer-label">Time:</span>
          <span class="timer-val idle" id="eng-timer">&mdash;</span>
        </div>
      </div>
    </div>

    <div class="demo-controls">
      <button class="run-btn" id="run-btn" onclick="runDemo()">&#9654; Run both</button>
      <span class="demo-note">Calling <code>api.railway-stations.org</code> in real time</span>
    </div>
  </div>
</section>

<!-- THE PATTERN -->
<section id="pattern" class="s-white">
  <div class="container">
    <div class="eyebrow">The Pattern</div>
    <h2 class="s-heading">Five antipatterns the engine solves</h2>
    <p class="s-sub">The same endpoints, two implementations. Each section shows the real classes from the project.</p>

    <div class="patron-grid">

      <!-- 1. CONFIGURATION -->
      <div>
        <div class="patron-header">
          <div class="patron-num">1</div>
          <div class="patron-meta">
            <h3>Integration configuration</h3>
            <div class="anti">&#10007; The base URL and paths live hardcoded in each method. There&rsquo;s no single place to see which endpoints exist.</div>
            <div class="sol">&#10003; One YAML file per integration declares base_url, paths and auth. Complete contract at a glance.</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>Without pattern</div>
            <div class="file-label">src/Traditional/RailwayApiService.php</div>
            <div class="code-block"><span class="kw">namespace</span> App\\Traditional;

<span class="kw">use</span> <span class="cls">Symfony\\Contracts\\HttpClient\\HttpClientInterface</span>;

<span class="kw">class</span> <span class="cls">RailwayApiService</span>
{
    <span class="cm">// Base URL lives here, not in any config file.</span>
    <span class="kw">private const</span> <span class="key">BASE</span> = <span class="bad-hl"><span class="str">'https://api.railway-stations.org'</span></span>;

    <span class="kw">private</span> <span class="cls">array</span> <span class="var">$countryCache</span> = [];

    <span class="kw">public function</span> <span class="fn">__construct</span>(
        <span class="kw">private</span> <span class="cls">HttpClientInterface</span> <span class="var">$http</span>,
    ) {}

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
        <span class="var">$this</span>-&gt;<span class="var">countryCache</span>[<span class="var">$countryCode</span>] = <span class="var">$stations</span>;
        <span class="kw">return</span> <span class="var">$stations</span>;
    }

    <span class="kw">public function</span> <span class="fn">fetchStation</span>(<span class="cls">string</span> <span class="var">$countryCode</span>, <span class="cls">string</span> <span class="var">$stationId</span>): ?<span class="cls">array</span>
    {
        <span class="var">$raw</span> = <span class="var">$this</span>-&gt;<span class="var">http</span>
            -&gt;<span class="fn">request</span>(<span class="bad-hl"><span class="str">'GET'</span>,
                <span class="str">self::BASE . '/photoStationById/'
                    . </span><span class="var">$countryCode</span> <span class="str">. '/' . </span><span class="var">$stationId</span></span>)
            -&gt;<span class="fn">toArray</span>();
        <span class="var">$base</span>     = <span class="var">$raw</span>[<span class="str">'photoBaseUrl'</span>];
        <span class="var">$stations</span> = <span class="var">$raw</span>[<span class="str">'stations'</span>] ?? [];
        <span class="kw">if</span> (<span class="fn">empty</span>(<span class="var">$stations</span>)) { <span class="kw">return null</span>; }
        <span class="var">$s</span>               = <span class="var">$stations</span>[0];
        <span class="var">$s</span>[<span class="str">'_photoBase'</span>] = <span class="var">$base</span>;
        <span class="var">$s</span>[<span class="str">'_hasPhoto'</span>]  = <span class="fn">isset</span>(<span class="var">$s</span>[<span class="str">'photos'</span>][0]);
        <span class="var">$s</span>[<span class="str">'_photoUrl'</span>]  = <span class="fn">isset</span>(<span class="var">$s</span>[<span class="str">'photos'</span>][0])
            ? <span class="var">$base</span> . <span class="var">$s</span>[<span class="str">'photos'</span>][0][<span class="str">'path'</span>]
            : <span class="kw">null</span>;
        <span class="kw">return</span> <span class="var">$s</span>;
    }
}</div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>Engine pattern</div>
            <div class="file-label">src/Engine/Infrastructure/Integrations/RailwayStations/RailwayStations.yaml</div>
            <div class="code-block"><span class="good-hl"><span class="key">GetStats</span>:
    <span class="key">action</span>: <span class="str">App\\...\\GetStatsAction</span>
    <span class="key">method</span>: <span class="str">GET</span>
    <span class="key">path</span>:   <span class="str">/stats</span></span>

<span class="good-hl"><span class="key">GetStationsByCountry</span>:
    <span class="key">action</span>: <span class="str">App\\...\\GetStationsByCountryAction</span>
    <span class="key">method</span>: <span class="str">GET</span>
    <span class="key">path</span>:   <span class="str">/photoStationsByCountry/{country}</span></span>

<span class="good-hl"><span class="key">GetStationById</span>:
    <span class="key">action</span>: <span class="str">App\\...\\GetStationByIdAction</span>
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
        <div class="insight"><strong>Why it matters:</strong> with 20 endpoints, finding which one calls which URL requires reading every method of the God class. With YAML, a new developer opens one file and sees the complete contract. If you change <code>base_url</code> or add authentication, there is a single point of change.</div>
      </div>

      <!-- 2. ROUTES -->
      <div>
        <div class="patron-header">
          <div class="patron-num">2</div>
          <div class="patron-meta">
            <h3>Route building with parameters</h3>
            <div class="anti">&#10007; Concatenating strings to build URLs is prone to silent typos. A <code>null</code> produces a valid but semantically incorrect URL.</div>
            <div class="sol">&#10003; <code>{placeholder}</code> templates in YAML resolved by <code>DefaultActionContext</code>. The engine throws an immediate exception if a parameter is missing.</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>Without pattern</div>
            <div class="file-label">src/Traditional/RailwayApiService.php &mdash; URL methods</div>
            <div class="code-block"><span class="cm">// One parameter in the path</span>
<span class="kw">public function</span> <span class="fn">fetchStations</span>(<span class="cls">string</span> <span class="var">$countryCode</span>): <span class="cls">array</span>
{
    <span class="var">$raw</span> = <span class="var">$this</span>-&gt;<span class="var">http</span>-&gt;<span class="fn">request</span>(
        <span class="str">'GET'</span>,
        <span class="bad-hl"><span class="str">self::BASE . '/photoStationsByCountry/' . </span><span class="var">$countryCode</span></span>
    )-&gt;<span class="fn">toArray</span>();
}

<span class="cm">// Two parameters in the path</span>
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

<span class="cm">// If $stationId === null:
// &rarr; /photoStationById/de/
// &rarr; HTTP 404 with no descriptive exception.
// The error surfaces late, far from the source.</span></div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>Engine pattern</div>
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
    \\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetStationByIdResponse</span>);
    <span class="kw">return</span> <span class="var">$response</span>;
}

<span class="cm">// If 'stationId' is missing: immediate, descriptive exception
// before the HTTP call is made.</span></div>
          </div>
        </div>
        <div class="insight"><strong>Why it matters:</strong> string concatenation fails silently. The engine&rsquo;s placeholders are contracts: if one is missing, the error is immediate and descriptive, not a mysterious 404 two layers below.</div>
      </div>

      <!-- 3. MAPPING -->
      <div>
        <div class="patron-header">
          <div class="patron-num">3</div>
          <div class="patron-meta">
            <h3>Response mapping</h3>
            <div class="anti">&#10007; Raw API fields (<code>'title'</code>, <code>'photos'</code>, <code>'photoBaseUrl'</code>) leak to all layers. If the API renames a field, the error appears in multiple files.</div>
            <div class="sol">&#10003; One <code>Mapper</code> accesses the raw fields. The rest of the code talks to typed DTOs.</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>Without pattern</div>
            <div class="file-label">src/Traditional/Controller/GetStationsByCountryController.php</div>
            <div class="code-block"><span class="kw">foreach</span> (<span class="var">$stations</span> <span class="kw">as</span> <span class="var">$s</span>) {
    <span class="var">$result</span>[] = [
        <span class="str">'id'</span>        =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="str">'id'</span></span>],
        <span class="str">'title'</span>     =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="str">'title'</span></span>],       <span class="cm">// raw API field</span>
        <span class="str">'lat'</span>       =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="str">'lat'</span></span>],
        <span class="str">'lon'</span>       =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="str">'lon'</span></span>],         <span class="cm">// not 'lng', not 'longitude'</span>
        <span class="str">'has_photo'</span> =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="key">'_hasPhoto'</span></span>],  <span class="cm">// private convention</span>
        <span class="str">'photo_url'</span> =&gt; <span class="var">$s</span>[<span class="bad-hl"><span class="key">'_photoUrl'</span></span>],  <span class="cm">// private convention</span>
    ];
}
<span class="cm">// If the API renames 'title' to 'name': search and fix EVERY
// file that accesses the array. How many are there?</span></div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>Engine pattern</div>
            <div class="file-label">src/Engine/.../GetStationsByCountryMapper.php</div>
            <div class="code-block"><span class="kw">final class</span> <span class="cls">GetStationsByCountryMapper</span> <span class="kw">extends</span> <span class="cls">AbstractMapper</span>
{
    <span class="kw">protected static function</span> <span class="fn">transform</span>(
        <span class="cls">AbstractAction</span> <span class="var">$action</span>, <span class="cls">array</span> <span class="var">$response</span>
    ): <span class="cls">ResponseInterface</span> {
        <span class="var">$photoBaseUrl</span> = <span class="var">$response</span>[<span class="good-hl"><span class="str">'photoBaseUrl'</span></span>];  <span class="cm">// only place</span>
        <span class="var">$stations</span> = <span class="fn">array_map</span>(
            <span class="kw">fn</span>(<span class="cls">array</span> <span class="var">$s</span>) =&gt; <span class="cls">StationDto</span>::<span class="fn">fromApiData</span>(<span class="var">$s</span>, <span class="var">$photoBaseUrl</span>),
            <span class="var">$response</span>[<span class="good-hl"><span class="str">'stations'</span></span>],              <span class="cm">// only place</span>
        );
        <span class="kw">return new</span> <span class="cls">GetStationsByCountryResponse</span>(<span class="var">$stations</span>);
    }
}</div>
            <div class="file-label">src/Engine/.../StationDto.php</div>
            <div class="code-block"><span class="kw">final readonly class</span> <span class="cls">StationDto</span>
{
    <span class="kw">public static function</span> <span class="fn">fromApiData</span>(<span class="cls">array</span> <span class="var">$station</span>, <span class="cls">string</span> <span class="var">$photoBaseUrl</span>): <span class="cls">self</span>
    {
        <span class="var">$firstPhoto</span> = <span class="var">$station</span>[<span class="good-hl"><span class="str">'photos'</span></span>][0] ?? <span class="kw">null</span>;  <span class="cm">// only place</span>
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
}
<span class="cm">// If the API renames 'title' to 'name': only this line changes.
// No other file touches raw API fields.</span></div>
          </div>
        </div>
        <div class="insight"><strong>Why it matters:</strong> without a mapper, knowledge of the API fields leaks into every class that processes the response. With the engine, <code>StationDto::fromApiData()</code> is the only point of contact. If the API renames a field, there is exactly one place to fix.</div>
      </div>

      <!-- 4. ACL -->
      <div>
        <div class="patron-header">
          <div class="patron-num">4</div>
          <div class="patron-meta">
            <h3>Anti-Corruption Layer</h3>
            <div class="anti">&#10007; The controller imports the HTTP client directly. Changing the API provider means touching every controller that consumes it.</div>
            <div class="sol">&#10003; <code>StationService</code> is the only boundary between the domain and the integration. Controllers only see their own domain objects.</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>Without pattern</div>
            <div class="file-label">src/Traditional/Controller/GetStationsByCountryController.php</div>
            <div class="code-block"><span class="kw">namespace</span> App\\Traditional\\Controller;

<span class="bad-hl"><span class="kw">use</span> <span class="cls">App\\Traditional\\RailwayApiService</span>;</span>

<span class="kw">class</span> <span class="cls">GetStationsByCountryController</span>
{
    <span class="kw">public function</span> <span class="fn">__construct</span>(<span class="kw">private</span> <span class="bad-hl"><span class="cls">RailwayApiService</span></span> <span class="var">$api</span>) {}

    #[<span class="cls">Route</span>(<span class="str">'/traditional/stations/{country}'</span>)]
    <span class="kw">public function</span> <span class="fn">__invoke</span>(<span class="cls">string</span> <span class="var">$country</span>): <span class="cls">JsonResponse</span>
    {
        <span class="var">$stations</span> = <span class="var">$this</span>-&gt;<span class="var">api</span>-&gt;<span class="fn">fetchStations</span>(<span class="var">$country</span>);
        <span class="cm">// maps raw _hasPhoto, _photoUrl conventions...</span>
        <span class="kw">return new</span> <span class="cls">JsonResponse</span>(<span class="var">$result</span>);
    }
}
<span class="cm">// Switch API &rarr; touch this controller,
// and all others that do the same.</span></div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>Engine pattern</div>
            <div class="file-label">src/Engine/Controller/GetStationsByCountryController.php</div>
            <div class="code-block"><span class="kw">namespace</span> App\\Engine\\Controller;

<span class="good-hl"><span class="kw">use</span> <span class="cls">App\\Engine\\Application\\StationService</span>;</span>

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
<span class="cm">// Switch API &rarr; StationService absorbs the change.
// This controller does not change.</span></div>
            <div class="file-label">src/Engine/Application/StationService.php &mdash; Anti-Corruption Layer</div>
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
        <div class="insight"><strong>Why it matters:</strong> without an ACL, the controller is coupled to <code>RailwayApiService</code> and its private conventions (<code>_hasPhoto</code>). With <code>StationService</code> as the only boundary, controllers only import domain objects and the cost of switching providers is reduced to a single file.</div>
      </div>

      <!-- 5. BATCH -->
      <div>
        <div class="patron-header">
          <div class="patron-num">5</div>
          <div class="patron-meta">
            <h3>Request batching</h3>
            <div class="anti">&#10007; The sequential <code>foreach</code> blocks: each request waits for the previous one. Total time scales linearly.</div>
            <div class="sol">&#10003; <code>sendManyOrFail()</code> dispatches all in parallel. Total time &asymp; the slowest request, regardless of the number of items.</div>
          </div>
        </div>
        <div class="split">
          <div class="cpanel bad">
            <div class="cpanel-head"><span class="panel-dot"></span>Without pattern</div>
            <div class="file-label">src/Traditional/Controller/GetStationsBatchController.php</div>
            <div class="code-block"><span class="kw">foreach</span> (<span class="var">$pairs</span> <span class="kw">as</span> <span class="var">$pair</span>) {
    [<span class="var">$country</span>, <span class="var">$stationId</span>] = <span class="fn">explode</span>(<span class="str">'/'</span>, <span class="var">$pair</span>, 2) + [<span class="str">''</span>, <span class="str">''</span>];

    <span class="bad-hl"><span class="cm">// HTTP request &mdash; others wait here, blocked</span>
    <span class="var">$s</span> = <span class="var">$this</span>-&gt;<span class="var">api</span>-&gt;<span class="fn">fetchStation</span>(<span class="var">$country</span>, <span class="var">$stationId</span>);</span>

    <span class="var">$result</span>[<span class="var">$pair</span>] = [
        <span class="str">'title'</span> =&gt; <span class="var">$s</span>[<span class="str">'title'</span>], <span class="str">'lat'</span> =&gt; <span class="var">$s</span>[<span class="str">'lat'</span>],
    ];
}
<span class="cm">//  3 stations &times; 250ms = ~750ms
// 10 stations &times; 250ms = ~2500ms  &larr; scales linearly</span></div>
          </div>
          <div class="cpanel good">
            <div class="cpanel-head"><span class="panel-dot"></span>Engine pattern</div>
            <div class="file-label">src/Engine/.../RailwayStationsIntegration.php &mdash; getManyStationsById()</div>
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

    <span class="good-hl"><span class="cm">// all go out at the same time &mdash; total time &asymp; the slowest</span>
    <span class="kw">return</span> <span class="var">$this</span>-&gt;<span class="var">engine</span>-&gt;<span class="fn">sendManyOrFail</span>(<span class="var">$requests</span>);</span>
}
<span class="cm">//  3 stations &rarr; ~250ms   (the slowest, not the sum)
// 10 stations &rarr; ~250ms   (does not scale)</span></div>
          </div>
        </div>
        <div class="insight"><strong>Why it matters:</strong> <code>sendManyOrFail()</code> dispatches in parallel. The default REST client already implements <code>BatchClientInterface</code> &mdash; zero additional configuration. If one request fails, the exception identifies exactly which one, and the rest of the batch has already executed.</div>
      </div>

    </div>
  </div>
</section>

<!-- SUMMARY -->
<section id="summary" class="s-light">
  <div class="container">
    <div class="eyebrow">Summary</div>
    <h2 class="s-heading">Without pattern vs Engine pattern</h2>
    <table class="summary-table">
      <thead>
        <tr><th>Concept</th><th>Without pattern</th><th>Engine pattern</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>Endpoint declaration</td>
          <td class="tbad"><span class="cross">&#10007;</span> Scattered across God class methods</td>
          <td class="tgood"><span class="check">&#10003;</span> One YAML file per integration</td>
        </tr>
        <tr>
          <td>URL building</td>
          <td class="tbad"><span class="cross">&#10007;</span> String concatenation, fails silently</td>
          <td class="tgood"><span class="check">&#10003;</span> <code>{placeholders}</code> validated at runtime</td>
        </tr>
        <tr>
          <td>API fields in code</td>
          <td class="tbad"><span class="cross">&#10007;</span> Leaked to all layers</td>
          <td class="tgood"><span class="check">&#10003;</span> Encapsulated in <code>Mapper</code> + <code>DTO</code></td>
        </tr>
        <tr>
          <td>Return type</td>
          <td class="tbad"><span class="cross">&#10007;</span> <code>array&lt;string, mixed&gt;</code> + <code>_</code> conventions</td>
          <td class="tgood"><span class="check">&#10003;</span> Typed <code>ResponseInterface</code></td>
        </tr>
        <tr>
          <td>Anti-Corruption Layer</td>
          <td class="tbad"><span class="cross">&#10007;</span> None &mdash; controller coupled to HTTP client</td>
          <td class="tgood"><span class="check">&#10003;</span> <code>StationService</code> as the only boundary</td>
        </tr>
        <tr>
          <td>Auth (Bearer, Basic, OAuth2)</td>
          <td class="tbad">Manual headers in each <code>request()</code></td>
          <td class="tgood"><span class="check">&#10003;</span> Declared in YAML, managed by the engine</td>
        </tr>
        <tr>
          <td>Adding a new endpoint</td>
          <td class="tbad">Method + URL + parsing + mapping scattered</td>
          <td class="tgood"><span class="check">&#10003;</span> Action + Mapper + Response + 3 YAML lines</td>
        </tr>
        <tr>
          <td>Batch</td>
          <td class="tbad"><span class="cross">&#10007;</span> Sequential <code>foreach</code>, linear time</td>
          <td class="tgood"><span class="check">&#10003;</span> <code>sendManyOrFail()</code>, constant time</td>
        </tr>
      </tbody>
    </table>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <span class="footer-brand">Integration<span>Engine</span></span>
    <p>
      <a href="https://integrationengine.dev" target="_blank">integrationengine.dev</a>
      &nbsp;&middot;&nbsp;
      <a href="https://github.com/CarlosGude/integrationEngine" target="_blank">GitHub</a>
      &nbsp;&middot;&nbsp;
      <a href="https://packagist.org/packages/carlosgude/integration-engine" target="_blank">Packagist</a>
      &nbsp;&middot;&nbsp;
      Data: <a href="https://api.railway-stations.org" target="_blank">api.railway-stations.org</a>
    </p>
  </div>
</footer>

<script>${JS}<\/script>
</body>
</html>`;
}
