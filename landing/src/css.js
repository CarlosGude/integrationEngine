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
  display: flex; align-items: center; padding: 0 1.5rem; gap: 1.5rem;
}
.topnav-brand {
  font-family: 'Inter', sans-serif;
  font-weight: 800; font-size: .95rem; color: #fff;
  text-decoration: none; margin-right: auto; white-space: nowrap;
  letter-spacing: -.01em;
}
.topnav-brand span { color: var(--blue-light); }
.topnav-links { display: flex; align-items: center; gap: 1.25rem; }
.topnav-links a { font-size: .82rem; color: var(--muted); text-decoration: none; transition: color .2s; white-space: nowrap; }
.topnav-links a:hover { color: #fff; }
.topnav-sep { width: 1px; height: 14px; background: var(--border); flex-shrink: 0; }
.lang-pill {
  display: inline-flex; align-items: center; gap: 6px;
  font-family: 'JetBrains Mono', "SFMono-Regular", Consolas, monospace;
  font-size: .72rem;
}
.lang-opt {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 7px; border-radius: 4px;
  border: 1px solid transparent;
  text-decoration: none; cursor: pointer;
  transition: opacity .15s, border-color .15s;
}
.lp-flag { font-size: .88rem; }
.lp-string {
  display: inline-flex; align-items: center; gap: 2px;
  background: #000; border-radius: 2px; padding: 1px 4px;
}
.lp-bracket { color: #3a5470; }
.lp-code { font-weight: 700; letter-spacing: .06em; }
.lang-inactive .lp-code { color: var(--muted); }
.lang-inactive:hover { border-color: rgba(255,255,255,0.08); }
.lang-active { border-color: rgba(255,255,255,0.15); }
.lang-active .lp-code { color: #7dd3fc; }
@media (max-width: 680px) { .topnav-links { display: none; } }

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

/* ── PROBLEM ── */
.problems-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.5rem; margin-top: 2rem; }
@media (max-width: 700px) { .problems-grid { grid-template-columns: 1fr; } }
.problem-card { background: #fff; border: 1px solid #dde3ec; border-radius: 12px; padding: 1.5rem; transition: box-shadow .2s, transform .2s; }
.problem-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); transform: translateY(-2px); }
.problem-icon { font-size: 1.75rem; margin-bottom: .75rem; }
.problem-card h3 { font-size: 1rem; font-weight: 700; color: var(--navy); margin-bottom: .5rem; }
.problem-card p { font-size: .87rem; color: #4a5568; line-height: 1.65; }
.problem-card code { background: #eef2ff; color: #1d4ed8; border-radius: 3px; padding: 0 4px; font-size: .8rem; font-family: 'JetBrains Mono', monospace; }

/* ── STRUCTURE ── */
.struct-split { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2rem; }
@media (max-width: 700px) { .struct-split { grid-template-columns: 1fr; } }
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
.code-block { background: #0d1117; padding: .9rem 1.1rem; font-family: 'JetBrains Mono', monospace; font-size: .72rem; line-height: 1.75; white-space: pre; overflow-x: auto; }
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

/* ── SUMMARY TABLE ── */
.summary-table { width: 100%; border-collapse: collapse; font-size: .85rem; margin-top: 1.5rem; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.summary-table th { text-align: left; padding: .8rem 1.1rem; background: #f1f5f9; color: #64748b; font-weight: 600; font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; }
.summary-table td { padding: .8rem 1.1rem; border-top: 1px solid #f1f5f9; }
.summary-table tr:hover td { background: #fafbff; }
.summary-table td:first-child { color: #64748b; font-size: .82rem; }
.summary-table code { font-family: 'JetBrains Mono', monospace; font-size: .78rem; background: #f1f5f9; padding: 0 4px; border-radius: 3px; }
.tbad  { color: #dc2626; }
.tgood { color: #1d4ed8; }
.check { color: #16a34a; font-weight: 700; }
.cross { color: #dc2626; }

/* ── FOOTER ── */
footer { background: var(--navy); padding: 2.5rem 1.5rem; text-align: center; border-top: 1px solid var(--border); }
.footer-brand { color: #fff; font-weight: 800; font-size: 1rem; display: block; margin-bottom: .6rem; }
.footer-brand span { color: var(--blue-light); }
footer p { color: var(--muted); font-size: .82rem; }
footer a { color: var(--blue-light); text-decoration: none; }
footer a:hover { text-decoration: underline; }
`;
