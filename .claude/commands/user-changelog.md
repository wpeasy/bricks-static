---
description: (project-local) Generate per-product user-facing version snapshots — CHANGELOG_FREE_VERSION.{md,html} at the repo root from CHANGELOG.md, and CHANGELOG_PRO_VERSION.{md,html} under pro/ from pro/CHANGELOG.md. Each is the LATEST release only, overwritten in full every run. Overrides the global user-changelog for this repo.
---

This repo ships **two** products from one codebase, each with its own technical
changelog, so the user-facing changelogs are split per product. There are **no
cumulative USER files** here — only single-release VERSION snapshots, one per
product, each overwritten in full on every run.

## Outputs (overwrite in full; latest release only)

| Source (technical) | Outputs | Location |
|---|---|---|
| `CHANGELOG.md` (Free) | `CHANGELOG_FREE_VERSION.md` + `.html` | repo root |
| `pro/CHANGELOG.md` (Pro) | `CHANGELOG_PRO_VERSION.md` + `.html` | `pro/` |

Always write all four (or the pair for whichever product you're regenerating).
No `CHANGELOG_USER.*`, no `CHANGELOG_VERSION.*`, no watermark/append-only logic —
each run takes the topmost release in the source and overwrites the snapshot.

## Translate to plain end-user English

For each product, take the **topmost release** in its technical changelog and
translate it for end users:

- **Hierarchy:** H1 `# Changelog` → H2 `## vX.Y.Z (YYYY-MM-DD)` (date in parens; no em-dash/hyphen between version and date) → one **H3 per change**: `### New`, `### Fixed`, `### Improved`, `### Deprecated`, `### Removed`. No bulleted lists under a status — each change is its own H3.
- **Sort H3s by status order:** New → Fixed → Improved → Deprecated → Removed.
- **No separators:** no `---` / `***` / `<hr>` anywhere; headings do all the separation.
- **INCLUDE:** user-visible features, behaviour changes, bug fixes users would hit, deprecations/removals, migration notes. **EXCLUDE:** internal refactors, build/tooling, type fixes, and security-vector detail (a single neutral line is fine if a release is entirely internal). Map a technical `Changed` → `Improved`.
- **No intro paragraph** — go straight from H1 to the latest version's H2.

## Pro extra — framing + link to the Free changelog

The **Pro** snapshot must LEAD its release with a framing `### New` entry making clear that Pro **unlocks every limit in the free plugin** (pages, destinations, replacement types, gzip output) **and adds its own extra features** on top (e.g. remote pruning, sitemaps, licensed automatic updates) — i.e. everything in the snapshot is included when Pro is active alongside the free plugin. Put this first, before the individual feature entries.



The **Pro** snapshot (`pro/CHANGELOG_PRO_VERSION.{md,html}`) MUST end with a
trailing paragraph (NOT an H3 status) referencing the free plugin's published
changelog:

> Bricks Static Pro requires the free Bricks Static plugin. Its changelog is published at https://brxprod.com/bricks-static-changelog/

In the `.html` twin, render it as a final `<p>` with an `<a href="https://brxprod.com/bricks-static-changelog/">…</a>`. The **Free** snapshot does NOT include this note.

## HTML twins

Content-only fragments: start at `<h1>`, end at the last element. No
`<!doctype>`/`<html>`/`<head>`/`<body>`, no styles, no classes, no `<script>`,
no `<hr>`. Element map: `#`→`<h1>`, `##`→`<h2>`, `###`→`<h3>`, paragraph→`<p>`,
`**bold**`→`<strong>`, `` `code` ``→`<code>`, `[t](u)`→`<a href="u">t</a>`.
HTML-escape `<`, `>`, `&`, `"`.

## Notes

- These four files are publishing/dev artifacts — they are **not** shipped in the plugin zips (the `scripts/make-zips.mjs` allowlist excludes `CHANGELOG_*`).
- Run on every version bump; the project-local `/commit-version` calls this for the side(s) it bumped (Free → the root pair, Pro → the `pro/` pair).
