export const CSS = `
:root {
  --navy:       #0d1b2e;
  --navy-mid:   #122040;
  --navy-light: #1c3057;
  --blue:       #2f6fbd;
  --blue-light: #4a8fd4;
  --red:        #c94f2c;
  --text:       #e8edf3;
  --muted:      #8fa3bd;
  --border:     #1e3352;
  --code-bg:    #0b1622;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Inter', system-ui, sans-serif;
  background: #f8fafc;
  color: #1a2a4a;
  line-height: 1.6;
  padding-top: 48px;
}

/* ── NAV ── */
.topnav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 200;
  height: 48px; background: var(--navy);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; padding: 0 1.5rem; gap: 1rem;
}
.topnav-brand {
  font-family: 'Inter', sans-serif;
  font-weight: 800; font-size: .95rem; color: #fff;
  text-decoration: none; white-space: nowrap; letter-spacing: -.01em; flex-shrink: 0;
}
.topnav-brand span { color: var(--blue-light); }
.topnav-links { display: flex; align-items: center; gap: 1.1rem; margin-right: auto; padding-left: .75rem; }
.topnav-links a { font-size: .82rem; color: var(--muted); text-decoration: none; transition: color .2s; white-space: nowrap; }
.topnav-links a:hover { color: #fff; }
.topnav-actions { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; }
.nav-btn { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--muted); text-decoration: none; border: 1px solid var(--border); border-radius: 6px; padding: .25rem .7rem; transition: color .2s, border-color .2s; white-space: nowrap; }
.nav-btn:hover { color: #e8edf3; border-color: var(--muted); }
.nav-btn-github { color: var(--blue-light); border-color: var(--blue); }
.nav-btn-github:hover { color: #fff; border-color: var(--blue-light); background: rgba(47,111,189,.15); }
.nav-btn svg { flex-shrink: 0; }
.nav-hamburger { display: none; background: none; border: none; color: var(--muted); font-size: 1.2rem; cursor: pointer; padding: .2rem .4rem; line-height: 1; margin-left: auto; }
.nav-hamburger:hover { color: #fff; }
.nav-mobile-menu { display: none; position: fixed; top: 48px; left: 0; right: 0; background: var(--navy-mid); border-bottom: 1px solid var(--border); z-index: 199; padding: .5rem 0; flex-direction: column; }
.nav-mobile-menu.open { display: flex; animation: fadeDown .2s ease; }
.nav-mobile-menu a { color: var(--muted); text-decoration: none; padding: .65rem 1.5rem; font-size: .9rem; border-bottom: 1px solid var(--border); transition: color .15s, background .15s; }
.nav-mobile-menu a:last-child { border-bottom: none; }
.nav-mobile-menu a:hover { color: #fff; background: rgba(255,255,255,.04); }
.nav-mobile-sep { border: none; border-top: 1px solid var(--border); margin: .25rem 0; }
@media (max-width: 680px) { .topnav-links { display: none; } .topnav-actions { display: none; } .nav-hamburger { display: block; margin-left: auto; } }

/* ── HERO ── */
.hero {
  background: radial-gradient(ellipse 80% 60% at 50% -10%, #1a3a6e 0%, var(--navy) 70%);
  color: var(--text); text-align: center;
  padding: 5rem 1.5rem 4.5rem; position: relative; overflow: hidden;
}
.hero::before {
  content: ''; position: absolute; top: -80px; left: 50%; transform: translateX(-50%);
  width: 600px; height: 300px;
  background: radial-gradient(ellipse, rgba(47,111,189,.35) 0%, transparent 70%);
  pointer-events: none; filter: blur(20px);
}
.hero-badge { position: relative; font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--blue-light); margin-bottom: 1.25rem; }
.hero h1 { position: relative; font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 800; line-height: 1.15; color: #fff; margin-bottom: 1.25rem; text-shadow: 0 0 60px rgba(74,143,212,.4); }
.hero p { position: relative; font-size: 1.05rem; color: var(--muted); max-width: 560px; margin: 0 auto 2.5rem; }
.install-box {
  display: inline-block; background: var(--code-bg);
  border: 1px solid var(--border); border-radius: 8px;
  padding: .75rem 1.75rem; font-family: 'JetBrains Mono', monospace;
  font-size: .9rem; color: #7dd3fc; margin-bottom: 1.75rem;
  cursor: pointer; user-select: all; transition: border-color .2s, box-shadow .2s; position: relative;
}
.install-box:hover { border-color: var(--blue-light); box-shadow: 0 0 18px rgba(74,143,212,.25); }
.install-box .copy-hint {
  position: absolute; top: -28px; left: 50%; transform: translateX(-50%);
  background: var(--blue); color: #fff;
  font-family: 'Inter', sans-serif; font-size: .72rem;
  padding: 2px 8px; border-radius: 4px;
  opacity: 0; pointer-events: none; transition: opacity .2s; white-space: nowrap;
}
.install-box.copied .copy-hint { opacity: 1; }
.hero-actions { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; position: relative; }
.btn-primary { background: var(--blue); color: #fff; font-weight: 700; font-size: .9rem; padding: .6rem 1.5rem; border-radius: 7px; text-decoration: none; transition: opacity .2s, transform .1s; }
.btn-primary:hover { opacity: .85; transform: translateY(-1px); }
.btn-outline { background: transparent; color: var(--text); font-weight: 600; font-size: .9rem; padding: .6rem 1.5rem; border-radius: 7px; text-decoration: none; border: 1px solid var(--border); transition: border-color .2s; }
.btn-outline:hover { border-color: var(--muted); }

/* ── SHARED ── */
.container { max-width: 1100px; margin: 0 auto; padding: 0 1.5rem; }
.s-light { background: #f8fafc; padding: 4rem 1.5rem; }
.s-white { background: #fff; padding: 4rem 1.5rem; }
.s-dark  { background: var(--navy); padding: 4rem 1.5rem; }
.eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--blue); margin-bottom: .5rem; }
.eyebrow.lt { color: var(--blue-light); }
.s-heading { font-size: clamp(1.5rem, 3vw, 2.2rem); font-weight: 800; color: var(--navy); margin-bottom: .75rem; }
.s-heading.lt { color: #fff; }
.s-sub { color: #4a5568; max-width: 600px; margin-bottom: 2rem; font-size: .95rem; }
.s-sub.lt { color: var(--muted); }

/* ── STRUCTURE ── */
.struct-panel { border: 1px solid rgba(74,143,212,.25); border-radius: 10px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.55); }
.struct-header { background: #152d52; padding: .55rem 1rem; font-size: .7rem; font-weight: 700; color: var(--blue-light); letter-spacing: .08em; }
.struct-panel pre { background: #060e1a; padding: 1rem 1.2rem; font-family: 'JetBrains Mono', monospace; font-size: .73rem; color: var(--muted); line-height: 1.8; overflow-x: auto; }
.struct-panel pre .hl  { color: #7dd3fc; }
.struct-panel pre .key { color: #7ee787; }
.struct-panel pre .val { color: #a5d6ff; }
.struct-panel pre .cm  { color: #4e7090; font-style: italic; }
.struct-panel pre .ph  { color: #f59e0b; }

/* ── PATTERN ── */
.patron-grid { display: flex; flex-direction: column; gap: 3.5rem; margin-top: 2rem; }
.patron-header { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem; }
.patron-num { width: 32px; height: 32px; border-radius: 50%; background: var(--blue); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; flex-shrink: 0; margin-top: 3px; }
.patron-meta h3 { font-size: 1.05rem; font-weight: 700; color: var(--navy); }
.patron-meta .anti { color: #dc2626; font-size: .82rem; font-weight: 600; margin-top: .2rem; }
.patron-meta .sol  { color: #1d4ed8; font-size: .82rem; font-weight: 600; }
.split { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: start; }
@media (max-width: 1000px) { .split { grid-template-columns: 1fr; } }
.cpanel { border-radius: 10px; overflow: hidden; border: 1px solid #dde3ec; }
.cpanel-head { padding: .5rem 1rem; font-size: .75rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
.cpanel.bad  .cpanel-head { background: #fef2f2; color: #dc2626; border-bottom: 1px solid #fecaca; }
.cpanel.good .cpanel-head { background: #eff6ff; color: #1d4ed8; border-bottom: 1px solid #bfdbfe; }
.panel-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.cpanel.bad  .panel-dot { background: #dc2626; }
.cpanel.good .panel-dot { background: #1d4ed8; }
.file-label { background: #161b22; color: #8a96a4; font-family: 'JetBrains Mono', monospace; font-size: .68rem; padding: .3rem 1.1rem; border-bottom: 1px solid #21262d; letter-spacing: .02em; }
.code-block { background: #0d1117; color: #c9d8e8; padding: .9rem 1.1rem; font-family: 'JetBrains Mono', monospace; font-size: .72rem; line-height: 1.75; white-space: pre; overflow-x: auto; }
.kw  { color: #ff7b72; }
.fn  { color: #d2a8ff; }
.str { color: #a5d6ff; }
.cm  { color: #8b9aaa; font-style: italic; }
.cls { color: #ffa657; }
.var { color: #79c0ff; }
.key { color: #7ee787; }
.bad-hl  { background: rgba(239,68,68,.14); border-left: 2px solid #ef4444; padding-left: 4px; }
.good-hl { background: rgba(59,130,246,.1);  border-left: 2px solid #3b82f6; padding-left: 4px; }
.insight { background: #eff6ff; border-left: 3px solid #2f6fbd; border-radius: 0 8px 8px 0; padding: .85rem 1.1rem; margin-top: 1rem; font-size: .84rem; color: #4a5568; }
.insight strong { color: var(--navy); }
.insight code { background: #dbeafe; color: #1d4ed8; border-radius: 3px; padding: 0 4px; font-family: 'JetBrains Mono', monospace; font-size: .77rem; }

/* ── CTA ── */
.cta-section { padding: 4rem 1.5rem; text-align: center; }
.cta-inner { display: flex; flex-direction: column; align-items: center; }
.cta-inner .s-sub { margin-bottom: 2rem; }
.cta-actions { display: flex; justify-content: center; align-items: center; gap: 1rem; flex-wrap: wrap; }
.cta-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .75rem 1.75rem; font-size: .9rem; }
.cta-icon { font-size: 1rem; line-height: 1; }
.cta-email-addr { opacity: .75; font-family: 'JetBrains Mono', monospace; font-size: .78rem; margin-left: .25rem; }

/* ── LANG PILL ── */
.lang-pill { display: flex; align-items: center; gap: 2px; background: #0d1622; border: 1px solid var(--border); border-radius: 6px; padding: 2px; flex-shrink: 0; }
.lang-opt { display: flex; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 4px; text-decoration: none; font-family: 'JetBrains Mono', monospace; font-size: .72rem; transition: background .15s, color .15s; white-space: nowrap; }
.lang-active   { background: var(--navy-light); color: #fff; }
.lang-inactive { color: var(--muted); }
.lang-inactive:hover { color: #fff; }
.lp-flag   { font-size: .85rem; line-height: 1; }
.lp-string { color: #a5d6ff; }
.lp-bracket { color: #8b9aaa; }
.lp-code   { color: #7dd3fc; }
.lang-active .lp-bracket, .lang-active .lp-code, .lang-active .lp-string { color: #fff; }

/* ── FOOTER ── */
footer { background: var(--navy); padding: 2.5rem 1.5rem; text-align: center; border-top: 1px solid var(--border); }
.footer-brand { color: #fff; font-weight: 800; font-size: 1rem; display: block; margin-bottom: .6rem; }
.footer-brand span { color: var(--blue-light); }
footer p { color: var(--muted); font-size: .82rem; }
footer a { color: var(--blue-light); text-decoration: none; }
footer a:hover { text-decoration: underline; }

/* ── HERO BENEFITS ── */
.hero-benefits { display: flex; justify-content: center; gap: .6rem; flex-wrap: wrap; margin-bottom: 2rem; position: relative; }
.benefit-pill { background: rgba(47,111,189,.18); border: 1px solid rgba(74,143,212,.3); color: #a5d6ff; font-size: .78rem; font-weight: 600; padding: .3rem .9rem; border-radius: 100px; white-space: nowrap; }

/* ── TRUST BAR ── */
.trust-bar { background: var(--navy-mid); border-bottom: 1px solid var(--border); padding: .55rem 1.5rem; display: flex; justify-content: center; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
.trust-item { display: flex; align-items: center; gap: .35rem; font-size: .75rem; color: var(--muted); }
.trust-item img { display: block; }
.trust-sep { color: var(--border); font-size: .9rem; line-height: 1; }
.trust-compat { font-family: 'JetBrains Mono', monospace; font-size: .72rem; color: var(--muted); }

/* ── QUICK COMPARE ── */
.compare-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2rem; }
@media (max-width: 700px) { .compare-wrap { grid-template-columns: 1fr; } }
.compare-col { border-radius: 12px; overflow: hidden; }
.compare-col.bad-col  { border: 1px solid #fecaca; }
.compare-col.good-col { border: 1px solid #bfdbfe; }
.compare-col-header { padding: .65rem 1.1rem; font-size: .82rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
.compare-col.bad-col  .compare-col-header { background: #fef2f2; color: #dc2626; border-bottom: 1px solid #fecaca; }
.compare-col.good-col .compare-col-header { background: #eff6ff; color: #1d4ed8; border-bottom: 1px solid #bfdbfe; }
.compare-item { padding: .6rem 1.1rem; font-size: .88rem; display: flex; align-items: center; gap: .65rem; color: #4a5568; border-bottom: 1px solid #f5f5f5; }
.compare-item:last-child { border-bottom: none; }
.ci-icon { font-size: .95rem; flex-shrink: 0; line-height: 1; }
.compare-col.bad-col  .ci-icon { color: #dc2626; }
.compare-col.good-col .ci-icon { color: #16a34a; }

/* ── PARALLEL SECTION ── */
.parallel-timing { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin: 2rem 0 1.75rem; }
@media (max-width: 600px) { .parallel-timing { grid-template-columns: 1fr; } }
.timing-block { border-radius: 12px; padding: 1.75rem 1.5rem; text-align: center; }
.timing-block.t-bad  { background: rgba(220,38,38,.08); border: 1px solid rgba(220,38,38,.25); }
.timing-block.t-good { background: rgba(22,163,74,.08);  border: 1px solid rgba(22,163,74,.25); }
.timing-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .09em; margin-bottom: .75rem; font-family: 'JetBrains Mono', monospace; }
.t-bad  .timing-label { color: #ef4444; }
.t-good .timing-label { color: #16a34a; }
.timing-number { font-size: 3.75rem; font-weight: 800; font-family: 'JetBrains Mono', monospace; line-height: 1; margin-bottom: .4rem; }
.t-bad  .timing-number { color: #dc2626; }
.t-good .timing-number { color: #16a34a; }
.timing-detail { font-size: .8rem; color: #64748b; }

/* ── PATTERN COLLAPSIBLE ── */
.pattern-expand-btn { width: 100%; margin-top: 1.5rem; background: #f8fafc; border: 2px dashed #bfdbfe; border-radius: 10px; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: center; gap: .75rem; cursor: pointer; font-family: 'Inter', sans-serif; font-size: .95rem; font-weight: 600; color: #1e3a5f; transition: background .2s, border-color .2s, color .2s; }
.pattern-expand-btn:hover { background: #eff6ff; border-color: var(--blue); color: var(--blue); }
.pattern-expand-btn.open { background: #eff6ff; border-color: var(--blue); border-style: solid; color: var(--blue); }
.peb-chevron { font-size: 1.3rem; transition: transform .35s ease; }
.pattern-expand-btn.open .peb-chevron { transform: rotate(180deg); }
.patron-collapsible { overflow: hidden; max-height: 0; opacity: 0; transition: max-height .6s ease, opacity .3s ease; }
.patron-collapsible.open { max-height: 20000px; opacity: 1; transition: max-height 1.4s ease-out, opacity .4s ease .05s; }

/* ── EXAMPLE PANELS ── */
.example-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 2rem; }
@media (max-width: 900px) { .example-panels { grid-template-columns: 1fr; } }
.example-panel-full { grid-column: 1 / -1; }
.example-code-panel { border-radius: 10px; overflow: hidden; border: 1px solid #dde3ec; }
.example-cta { margin-top: 1.5rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.link-arrow { color: var(--blue); font-weight: 600; font-size: .88rem; text-decoration: none; }
.link-arrow:hover { text-decoration: underline; }

/* ── THANKS SECTION ── */
.thanks-section { text-align: center; }
.thanks-section .eyebrow { justify-content: center; }
.thanks-section .s-heading { margin-bottom: 1rem; }
.thanks-p { max-width: 520px; margin: 0 auto; font-size: 1rem; color: #4a5568; line-height: 1.8; }

/* ── MID-PAGE CTA ── */
.mid-cta { text-align: center; padding: 2.25rem 1.5rem; background: #eff6ff; border-top: 1px solid #bfdbfe; border-bottom: 1px solid #bfdbfe; }
.mid-cta p { font-size: .95rem; color: #1e3a5f; margin-bottom: 1.1rem; font-weight: 500; }
.mid-cta-actions { display: flex; justify-content: center; align-items: center; gap: .85rem; flex-wrap: wrap; }
.mid-cta-outline { color: var(--navy); border-color: #93c5fd; }
.mid-cta-outline:hover { border-color: var(--navy); }
/* ── GET STARTED ── */
.start-sub { max-width: 600px; margin: .5rem auto 0; }
.steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem; }
@media (max-width: 700px) { .steps-grid { grid-template-columns: 1fr; } }
.step { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; }
.step-num { width: 28px; height: 28px; border-radius: 50%; background: var(--blue); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .8rem; margin-bottom: .85rem; flex-shrink: 0; }
.step h3 { font-size: 1rem; font-weight: 700; color: var(--navy); margin-bottom: .5rem; }
.step p { font-size: .84rem; color: #64748b; line-height: 1.65; margin-top: .5rem; flex: 1; }
.step-code { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: .45rem .85rem; font-family: 'JetBrains Mono', monospace; font-size: .73rem; color: #1e3a5f; margin-top: .35rem; }
.step a { display: inline-block; margin-top: .85rem; color: var(--blue); font-size: .84rem; font-weight: 600; text-decoration: none; }
.step a:hover { text-decoration: underline; }
.generator-panel { margin-top: 2rem; }
.generator-panel pre { font-size: .78rem; line-height: 1.75; }
.gen-docs-link { display: block; width: fit-content; margin: 1.25rem auto .75rem; }
@media (max-width: 680px) { .gen-docs-link { width: calc(100% - 2rem); text-align: center; box-sizing: border-box; } }
/* ── BUSINESS VALUE ── */
.biz-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem; }
@media (max-width: 700px) { .biz-grid { grid-template-columns: 1fr; } }
.biz-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.biz-stat { font-size: 1.65rem; font-weight: 800; color: var(--blue); letter-spacing: -.02em; margin-bottom: .55rem; }
.biz-card p { font-size: .84rem; color: #64748b; line-height: 1.65; }
/* ── EXTENSION POINTS ── */
.ext-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-top: 2rem; }
@media (max-width: 700px) { .ext-grid { grid-template-columns: 1fr; } }
.ext-card { background: rgba(255,255,255,.04); border: 1px solid rgba(74,143,212,.18); border-radius: 10px; padding: 1.25rem 1.35rem; }
.ext-iface { display: inline-block; font-family: 'JetBrains Mono', monospace; font-size: .78rem; font-weight: 600; color: #7dd3fc; background: rgba(47,111,189,.18); padding: .2rem .65rem; border-radius: 4px; margin-bottom: .6rem; }
.ext-card p { font-size: .83rem; color: var(--muted); line-height: 1.65; }
`;

