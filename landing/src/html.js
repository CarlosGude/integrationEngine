import { CSS } from './css.js';
import { JS }  from './client.js';
import en from './i18n/en.js';
import es from './i18n/es.js';

const translations = { en, es };

export function getHTML(lang = 'en') {
    const t = translations[lang] ?? translations.en;

    const problemCards = t.problems.map(p => `
      <div class="problem-card">
        <div class="problem-icon">&#127381;</div>
        <h3>${p.title}</h3>
        <p>${p.desc}</p>
      </div>`).join('');

    const summaryRows = t.summaryRows.map(r => `
        <tr>
          <td>${r.concept}</td>
          <td class="tbad">${r.without}</td>
          <td class="tgood">${r.engine}</td>
        </tr>`).join('');

    const isEs = lang === 'es';
    const docsHref = isEs
        ? 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION_ES.md'
        : 'https://github.com/CarlosGude/integrationEngine/blob/main/DOCUMENTATION.md';

    return `<!DOCTYPE html>
<html lang="${t.lang}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IntegrationEngine &mdash; Demo</title>
<meta name="description" content="One standard for every external API in your Symfony projects." />
<meta property="og:type"        content="website" />
<meta property="og:url"         content="https://integrationengine.dev/?lang=${lang}" />
<meta property="og:title"       content="IntegrationEngine &mdash; Demo" />
<meta property="og:description" content="One standard for every external API in your Symfony projects." />
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
    <a href="#pattern">${t.navPattern}</a>
    <span class="topnav-sep"></span>
    <a href="${docsHref}" target="_blank" rel="noopener">Docs</a>
    <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" rel="noopener">GitHub</a>
  </div>
  <div class="lang-pill">
    <a href="?lang=es" class="lang-opt ${isEs ? 'lang-active' : 'lang-inactive'}">
      <span class="lp-flag">&#127466;&#127480;</span>
      <span class="lp-string"><span class="lp-bracket">'</span><span class="lp-code">ES</span><span class="lp-bracket">'</span></span>
    </a>
    <a href="?lang=en" class="lang-opt ${!isEs ? 'lang-active' : 'lang-inactive'}">
      <span class="lp-flag">&#127468;&#127463;</span>
      <span class="lp-string"><span class="lp-bracket">'</span><span class="lp-code">EN</span><span class="lp-bracket">'</span></span>
    </a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-badge">Symfony Bundle &middot; PHP 8.2+ &middot; Symfony 7+</div>
  <h1>${t.heroH1}</h1>
  <p>${t.heroP}</p>
  <div class="install-box" onclick="copyInstall(this)">
    <span class="copy-hint">${t.copyHint}</span>
    composer require carlosgude/integration-engine
  </div>
  <div class="hero-actions">
    <a href="#pattern" class="btn-primary">${t.heroBtn1}</a>
    <a href="https://github.com/CarlosGude/integrationEngine" target="_blank" class="btn-outline">${t.heroBtn2}</a>
  </div>
</section>

<!-- THE PROBLEM -->
<section id="problem" class="s-light">
  <div class="container">
    <div class="eyebrow">${t.problemEyebrow}</div>
    <h2 class="s-heading">${t.problemH2}</h2>
    <p class="s-sub">${t.problemSub}</p>
    <div class="problems-grid">${problemCards}
    </div>
  </div>
</section>

<!-- THE SOLUTION -->
<section id="structure" class="s-dark">
  <div class="container">
    <div class="eyebrow lt">${t.structureEyebrow}</div>
    <h2 class="s-heading lt">${t.structureH2}</h2>
    <p class="s-sub lt">${t.structureSub}</p>
    <div class="struct-split">
      <div class="struct-panel">
        <div class="struct-header">${t.structureYamlHdr}</div>
        <pre><span class="cm">${t.cmContract}</span>

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
        <div class="struct-header">${t.structureDirHdr}</div>
        <pre>src/Infrastructure/Integrations/<span class="hl">RailwayStations</span>/
&boxvr;&boxh;&boxh; <span class="hl">RailwayStationsIntegration.php</span>  <span class="cm">${t.cmFacade}</span>
&boxvr;&boxh;&boxh; <span class="hl">RailwayStations.yaml</span>             <span class="cm">${t.cmActionMap}</span>
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

<!-- THE PATTERN -->
<section id="pattern" class="s-white">
  <div class="container">
    <div class="eyebrow">${t.patternEyebrow}</div>
    <h2 class="s-heading">${t.patternH2}</h2>
    <p class="s-sub">${t.patternSub}</p>

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
            <div class="code-block"><span class="kw">namespace</span> App\\Traditional;

<span class="kw">use</span> <span class="cls">Symfony\\Contracts\\HttpClient\\HttpClientInterface</span>;

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
    \\assert(<span class="var">$response</span> <span class="kw">instanceof</span> <span class="cls">GetStationByIdResponse</span>);
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
            <div class="code-block"><span class="kw">namespace</span> App\\Traditional\\Controller;

<span class="bad-hl"><span class="kw">use</span> <span class="cls">App\\Traditional\\RailwayApiService</span>;</span>

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
</section>

<!-- SUMMARY -->
<section id="summary" class="s-light">
  <div class="container">
    <div class="eyebrow">${t.summaryEyebrow}</div>
    <h2 class="s-heading">${t.summaryH2}</h2>
    <table class="summary-table">
      <thead>
        <tr>
          <th>${t.summaryThConcept}</th>
          <th>${t.summaryThWithout}</th>
          <th>${t.summaryThEngine}</th>
        </tr>
      </thead>
      <tbody>${summaryRows}
      </tbody>
    </table>
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
      <a href="https://integrationengine.dev" target="_blank">integrationengine.dev</a>
      &nbsp;&middot;&nbsp;
      <a href="https://github.com/CarlosGude/integrationEngine" target="_blank">GitHub</a>
      &nbsp;&middot;&nbsp;
      <a href="https://packagist.org/packages/carlosgude/integration-engine" target="_blank">Packagist</a>
    </p>
  </div>
</footer>

<script>${JS}<\/script>
</body>
</html>`;
}
