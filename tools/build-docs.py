#!/usr/bin/env python3
"""Build the single-file documentation editions: docs/documentation-{minimal,extended,full}.md.

Every source page is copied verbatim; only heading levels and links change. Links between
pages included in an edition become anchors inside the file; every other relative link is
rewritten to point at the repository file. Run `make docs-editions` after editing a page.
Exits non-zero when a link or anchor cannot be resolved.
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT_DIR = "docs"

PARTS = [
    ("Part I — Overview", "part-overview", [
        "README.md",
        "docs/documentation.md",
    ]),
    ("Part II — Getting started", "part-getting-started", [
        "docs/getting-started/actions.md",
        "docs/getting-started/context-and-path.md",
        "docs/getting-started/authorization.md",
        "docs/getting-started/mappers-and-responses.md",
        "docs/getting-started/batch-requests.md",
    ]),
    ("Part III — Configuration, clients and middleware", "part-reference", [
        "docs/configuration.md",
        "docs/advanced/architecture/clients.md",
        "docs/form-bodies.md",
        "docs/middleware.md",
        "docs/errors.md",
    ]),
    ("Part IV — Architecture", "part-architecture", [
        "docs/architecture.md",
        "docs/advanced/architecture/class-graph.md",
    ]),
    ("Part V — Resilience, security and observability", "part-operations", [
        "docs/resilience.md",
        "docs/advanced/resilience.md",
        "docs/security.md",
        "docs/lifecycle.md",
        "docs/observability.md",
        "docs/advanced/debugging.md",
    ]),
    ("Part VI — Inbound webhooks", "part-webhooks", [
        "docs/webhook.md",
    ]),
    ("Part VII — Quality, testing and tooling", "part-quality", [
        "docs/phpstan.md",
        "docs/testing.md",
        "docs/advanced/quality.md",
        "docs/advanced/ai-agent-usage.md",
        "CONTRIBUTING.md",
    ]),
    ("Part VIII — Roadmap", "part-roadmap", [
        "docs/roadmap.md",
        "docs/advanced/next-features.md",
    ]),
    ("Part IX — Architecture decision records", "part-adr", [
        "docs/adr/README.md",
    ] + sorted(
        "docs/adr/" + f for f in os.listdir(os.path.join(ROOT, "docs/adr"))
        if re.match(r"\d{4}-", f) and not f.startswith("0000-")
    )),
    ("Part X — Upgrade guides (historical)", "part-upgrades", [
        "docs/upgrade-4.0.md",
        "docs/upgrade-5.0.md",
        "docs/upgrade-5.1.md",
        "docs/upgrade-6.0.md",
        "docs/upgrade-7.0.md",
        "docs/migration-v7-client-registration.md",
        "docs/upgrade-8.0.md",
        "docs/upgrade-9.0.md",
    ]),
]

# Per edition, the parts it contains; a list overrides the part's documents.
EDITIONS = {
    "minimal": {
        "title": "IntegrationEngine — Documentation (minimal edition)",
        "parts": {"part-overview": ["README.md"], "part-getting-started": None, "part-reference": None},
        "scope": "The README, the getting-started guides and the configuration, client, middleware and error "
                 "references: enough to build and run an integration. Links to any other document point to the "
                 "repository file.",
    },
    "extended": {
        "title": "IntegrationEngine — Documentation (extended edition)",
        "parts": {pid: None for _, pid, _ in PARTS if pid not in ("part-adr", "part-upgrades")},
        "scope": "All current documentation. Architecture decision records and historical upgrade guides are "
                 "left out; links to them point to the repository files.",
    },
    "full": {
        "title": "IntegrationEngine — Documentation (full edition)",
        "parts": {pid: None for _, pid, _ in PARTS},
        "scope": "All current documentation plus architecture decision records and historical upgrade guides.",
    },
}

PART_NOTES = {
    "part-adr": "ADRs record *why* a boundary was chosen. Superseded ADRs are kept on purpose and are not a current API reference; the status table below is the source of truth.",
    "part-upgrades": "Historical migration documents. They intentionally mention APIs that no longer exist — for current behaviour use Parts I–VIII.",
}

# Navigation-only pages that are not copied: links to them land on their part instead.
REDIRECTS = {
    "docs/getting-started/README.md": "part-getting-started",
}

FENCE_OPEN = re.compile(r"^ {0,3}(`{3,}|~{3,})")
HEADING = re.compile(r"^(#{1,6})\s+(.*?)\s*#*\s*$")
LINK = re.compile(r"(!?)\[((?:[^\[\]]|\[[^\]]*\])*)\]\(([^)\s]+)((?:\s+\"[^\"]*\")?)\)")


def doc_id(path):
    p = path[len("docs/"):] if path.startswith("docs/") else path
    p = re.sub(r"\.md$", "", p).lower()
    return re.sub(r"[^a-z0-9]+", "-", p).strip("-")


def slug(text):
    """GitHub's heading anchor, so fragments written against the source page still resolve."""
    text = re.sub(r"\[([^\]]*)\]\([^)]*\)", r"\1", text)
    text = re.sub(r"<[^>]+>", "", text)
    text = re.sub(r"[^\w\- ]", "", text.lower())
    return text.replace(" ", "-")


def iter_lines(text):
    """Yield (line, in_fence) so headings and links inside code blocks are left alone."""
    fence = None
    for line in text.split("\n"):
        if fence is None:
            m = FENCE_OPEN.match(line)
            if m:
                fence = (m.group(1)[0], len(m.group(1)))
            yield line, fence is not None
            continue
        char, length = fence
        if re.match(r"^ {0,3}" + re.escape(char) + "{" + str(length) + r",}\s*$", line):
            fence = None
        yield line, True


def read(path):
    with open(os.path.join(ROOT, path), encoding="utf-8") as fh:
        return fh.read()


def build(edition):
    parts = [
        (title, pid, edition["parts"][pid] or docs)
        for title, pid, docs in PARTS if pid in edition["parts"]
    ]
    active_parts = {pid for _, pid, _ in parts}
    included = [p for _, _, docs in parts for p in docs]
    problems = []

    # Heading anchors per page, keyed by the anchor the source page exposes on GitHub.
    anchors, titles = {}, {}
    for p in included:
        seen, amap = {}, {}
        for line, in_fence in iter_lines(read(p)):
            m = None if in_fence else HEADING.match(line)
            if not m:
                continue
            s = slug(m.group(2))
            seen[s] = seen[s] + 1 if s in seen else 0
            unique = f"{s}-{seen[s]}" if seen[s] else s
            titles.setdefault(p, m.group(2))
            amap[unique] = f"{doc_id(p)}--{unique}"
        anchors[p] = amap

    def resolve_link(src, target):
        if re.match(r"^[a-z][a-z0-9+.-]*:", target, re.I):
            return target
        path, _, frag = target.partition("#")
        dest = src if path == "" else os.path.normpath(os.path.join(os.path.dirname(src), path))
        if path and os.path.isdir(os.path.join(ROOT, dest)) and os.path.isfile(os.path.join(ROOT, dest, "README.md")):
            dest = os.path.join(dest, "README.md")
        if dest in included:
            if not frag:
                return "#" + doc_id(dest)
            if frag not in anchors[dest]:
                problems.append(f"{src}: fragment '#{frag}' not found in {dest}")
                return "#" + doc_id(dest)
            return "#" + anchors[dest][frag]
        if REDIRECTS.get(dest) in active_parts:
            return "#" + REDIRECTS[dest]
        if not os.path.exists(os.path.join(ROOT, dest)):
            problems.append(f"{src}: target '{target}' does not exist")
        rel = os.path.relpath(os.path.join(ROOT, dest), os.path.join(ROOT, OUT_DIR))
        if os.path.isdir(os.path.join(ROOT, dest)):
            rel += "/"
        return rel + ("#" + frag if frag else "")

    def render(p):
        lines = list(iter_lines(read(p).rstrip("\n")))
        levels = [len(HEADING.match(l).group(1)) for l, fenced in lines if not fenced and HEADING.match(l)]
        # The page title becomes an H2 under its part; the rest start at H3.
        shift = 3 - (min(levels[1:]) if len(levels) > 1 else 2)
        ids = iter(anchors[p].values())
        out, first = [], True
        for line, in_fence in lines:
            m = None if in_fence else HEADING.match(line)
            if m and first:
                out += ["", f'<a id="{doc_id(p)}"></a>', f'<a id="{next(ids)}"></a>', "",
                        f"## {m.group(2)}", "", f"*Source: `{p}`*"]
                first = False
            elif m:
                out += ["", f'<a id="{next(ids)}"></a>', "", "#" * min(6, len(m.group(1)) + shift) + " " + m.group(2)]
            elif in_fence:
                out.append(line)
            else:
                out.append(LINK.sub(lambda mm: f"{mm.group(1)}[{mm.group(2)}]({resolve_link(p, mm.group(3))}{mm.group(4)})", line))
        return re.sub(r"\n{3,}", "\n\n", "\n".join(out)).strip("\n")

    out = [
        f"# {edition['title']}",
        "",
        f"> **Scope:** {edition['scope']}",
        ">",
        "> Single-file compilation of the repository documentation, generated by `tools/build-docs.py`. Every "
        "document is copied verbatim; only heading levels and links change: links between documents included "
        "here point to anchors inside this file, and links to code or to anything else point to the repository files.",
        ">",
        "> **Never included, in any edition:** `CHANGELOG.md` (release history), `docs/archived/` (historical plans, "
        "explicitly not current documentation), navigation-only READMEs (replaced by the contents below), the ADR "
        "template, `CLAUDE.md`, `landing/` and `.symfony-recipes/`.",
        ">",
        "> Do not edit this file: edit the source document named under each title and run `make docs-editions`.",
        "",
        '<a id="contents"></a>',
        "",
        "## Contents",
        "",
    ]
    for title, pid, docs in parts:
        out.append(f"- [{title}](#{pid})")
        out += [f"  - [{titles[p]}](#{doc_id(p)})" for p in docs]
    out.append("")
    for title, pid, docs in parts:
        out += ["---", "", f'<a id="{pid}"></a>', "", f"# {title}", ""]
        if pid in PART_NOTES:
            out += [f"> {PART_NOTES[pid]}", ""]
        out += [x for p in docs for x in (render(p), "")]
    result = "\n".join(out).rstrip("\n") + "\n"

    defined = {"contents"} | active_parts | {doc_id(p) for p in included}
    defined |= {i for amap in anchors.values() for i in amap.values()}
    problems += [f"dangling anchor #{t}" for t in re.findall(r"\]\(#([^)]+)\)", result) if t not in defined]

    return result, len(included), problems


def main():
    failed = False
    for name, edition in EDITIONS.items():
        result, count, problems = build(edition)
        path = os.path.join(OUT_DIR, f"documentation-{name}.md")
        with open(os.path.join(ROOT, path), "w", encoding="utf-8") as fh:
            fh.write(result)
        print(f"{path}: {count} documents, {result.count(chr(10))} lines")
        for problem in sorted(set(problems)):
            print(f"  {problem}", file=sys.stderr)
            failed = True
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
