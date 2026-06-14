export const CSS = `*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --navy:      #0d1b2e;
  --navy-mid:  #122040;
  --navy-light:#1c3057;
  --blue:      #2f6fbd;
  --blue-light:#4a8fd4;
  --red:       #c94f2c;
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
  align-items: center;
  gap: 1.25rem;
}
.topnav-links a {
  font-size: .82rem;
  color: var(--muted);
  text-decoration: none;
  transition: color .2s;
  white-space: nowrap;
}
.topnav-links a:hover { color: var(--white); }
.topnav-sep {
  width: 1px;
  height: 14px;
  background: var(--border);
  flex-shrink: 0;
}
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
.lang-inactive .lp-code { color: var(--muted); }
.lang-inactive:hover { border-color: rgba(255,255,255,0.08); }
.lang-active { border-color: rgba(255,255,255,0.15); }
.lang-active .lp-code { color: #7dd3fc; }

@media (max-width: 680px) {
  .topnav-links { display: none; }
}

h1, h2, h3, .section-title, .hero h1, .cta h2 {
  font-family: "Inter", sans-serif;
}

/* ── HERO ── */
.hero {
  position: relative;
  background: radial-gradient(ellipse 80% 60% at 50% -10%, #1a3a6e 0%, var(--navy) 70%);
  color: var(--text);
  text-align: center;
  padding: 3.5rem 1.5rem 3rem;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute;
  top: -80px; left: 50%;
  transform: translateX(-50%);
  width: 600px; height: 300px;
  background: radial-gradient(ellipse, rgba(47,111,189,.35) 0%, transparent 70%);
  pointer-events: none;
  filter: blur(20px);
}
.hero-badge {
  position: relative;
  font-size: .75rem;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: 1.5rem;
}
.hero h1 {
  position: relative;
  font-size: clamp(1.7rem, 5vw, 3.25rem);
  font-weight: 800;
  line-height: 1.2;
  color: var(--white);
  max-width: 760px;
  margin: 0 auto 1.25rem;
  text-shadow: 0 0 60px rgba(74,143,212,.4);
}
.hero p {
  position: relative;
  font-size: 1.05rem;
  color: var(--muted);
  max-width: 540px;
  margin: 0 auto 2rem;
}
.install-box {
  display: inline-block;
  max-width: calc(100% - 3rem);
  background: var(--code-bg);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: .7rem 1.5rem;
  font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
  font-size: .9rem;
  color: #7dd3fc;
  margin-bottom: 2rem;
  cursor: pointer;
  position: relative;
  overflow-x: auto;
  white-space: nowrap;
  transition: border-color .2s, box-shadow .2s;
}
.install-box:hover {
  border-color: var(--blue-light);
  box-shadow: 0 0 18px rgba(74,143,212,.25);
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
  display: block;
  width: fit-content;
  margin: 0 auto;
  background: var(--blue);
  color: #fff;
  font-family: "Inter", sans-serif;
  font-size: .9rem;
  font-weight: 700;
  padding: .6rem 1.5rem;
  border-radius: 6px;
  text-decoration: none;
  text-align: center;
  transition: opacity .2s, transform .1s;
}
.hero-gh-btn:hover { opacity: .85; transform: translateY(-1px); }

/* ── SECTIONS ── */
section { padding: 2.5rem 1.5rem; }
.container { max-width: 960px; margin: 0 auto; }

.section-label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .15em;
  text-transform: uppercase;
  color: var(--blue);
  margin-bottom: .6rem;
}
.section-title {
  font-size: clamp(1.4rem, 3.5vw, 2.1rem);
  font-weight: 800;
  color: var(--blue);
  margin-bottom: .6rem;
}
.section-sub {
  color: #4a5568;
  max-width: 640px;
  margin-bottom: 2rem;
}

/* ── PROBLEMA ── */
.problema { background: #f8fafc; }
.problems-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;
  background: #dde3ec;
  border: 1px solid #dde3ec;
  border-radius: 8px;
  overflow: hidden;
}
.problem-card { background: #fff; padding: 1.25rem; }
.problem-card h3 { font-size: .9rem; font-weight: 700; color: var(--red); margin-bottom: .4rem; }
.problem-card p  { font-size: .8rem; color: #4a5568; line-height: 1.5; }

/* ── COMO FUNCIONA ── */
.como { background: var(--white); }
.pipeline {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  background: var(--navy);
  border-radius: 8px;
  margin-bottom: 2rem;
  padding: 1.25rem 1.5rem;
  border: 1px solid var(--border);
  gap: .25rem;
}
.pipe-step { display: flex; align-items: center; }
.pipe-label {
  font-family: "SFMono-Regular", Consolas, monospace;
  font-size: .82rem;
  padding: .5rem 1rem;
  border-radius: 6px;
  color: var(--muted);
  background: var(--navy-light);
  border: 1px solid transparent;
  transition: color .3s, border-color .3s, box-shadow .3s;
  white-space: nowrap;
}
.pipe-label.pipe-active {
  color: #fff;
  border-color: var(--blue-light);
  box-shadow: 0 0 14px rgba(74,143,212,.45);
}
.pipe-label.highlight { background: var(--blue); color: #fff; font-weight: 700; }
.pipe-label.highlight.pipe-active {
  box-shadow: 0 0 20px rgba(47,111,189,.7);
  border-color: #7dd3fc;
}
.pipe-arrow {
  color: var(--blue-light);
  padding: 0 .35rem;
  font-size: 1.1rem;
  opacity: .4;
  transition: opacity .3s;
  flex-shrink: 0;
}
.pipe-arrow.pipe-arrow-active { opacity: 1; }

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1.25rem;
}
.feature-card { border: 1px solid #dde3ec; border-radius: 8px; padding: 1.25rem; }
.feature-card h3 { font-size: .9rem; font-weight: 700; color: var(--blue); margin-bottom: .4rem; }
.feature-card p  { font-size: .82rem; color: #4a5568; line-height: 1.55; }

/* ── CALL SITE ── */
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
  padding: .55rem 1.1rem;
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

/* ── CTA ── */
.cta {
  background: var(--navy);
  color: var(--text);
  text-align: center;
  padding: 3rem 1.5rem;
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
  padding: 1.25rem;
  font-size: .78rem;
}
footer a { color: var(--blue-light); text-decoration: none; }

/* ── MOBILE ── */
@media (max-width: 600px) {
  section { padding: 2rem 1.25rem; }
  .hero { padding: 2.5rem 1.25rem 2rem; }
  .hero p { font-size: .95rem; }
  .install-box { font-size: .75rem; padding: .6rem 1rem; }
  .hero-gh-btn { width: 100%; }

  /* pipeline vertical */
  .pipeline { flex-direction: column; align-items: center; gap: 0; padding: 1rem; }
  .pipe-step { flex-direction: column; align-items: center; }
  .pipe-arrow { transform: rotate(90deg); padding: .1rem 0; opacity: .5; }
  .pipe-arrow.pipe-arrow-active { opacity: 1; }

  /* problem cards: 1 columna */
  .problems-grid { grid-template-columns: 1fr; }

  /* feature cards: 1 columna */
  .features-grid { grid-template-columns: 1fr; }

  /* CTA buttons en columna */
  .cta-buttons { flex-direction: column; align-items: stretch; }
  .cta-buttons .btn { text-align: center; }

  .line-numbers { display: none; }
}
`;
