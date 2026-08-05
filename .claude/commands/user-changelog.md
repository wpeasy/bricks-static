---
description: (project-local) Generate a user-facing version snapshot — CHANGELOG_FREE_VERSION.{md,html} at the repo root from CHANGELOG.md. LATEST release only, overwritten in full every run. Overrides the global user-changelog for this repo.
---

There is **no cumulative USER file** here — only a single-release VERSION
snapshot, overwritten in full on every run.

## Output (overwrite in full; latest release only)

| Source (technical) | Outputs | Location |
|---|---|---|
| `CHANGELOG.md` | `CHANGELOG_FREE_VERSION.md` + `.html` | repo root |

Always write both. No `CHANGELOG_USER.*`, no `CHANGELOG_VERSION.*`, no
watermark/append-only logic — each run takes the topmost release in the
source and overwrites the snapshot.

## Translate to plain end-user English

Take the **topmost release** in `CHANGELOG.md` and translate it for end users:

- **Hierarchy:** H1 `# Changelog` → H2 `## vX.Y.Z (YYYY-MM-DD)` (date in parens; no em-dash/hyphen between version and date) → one **H3 per change**: `### New`, `### Fixed`, `### Improved`, `### Deprecated`, `### Removed`. No bulleted lists under a status — each change is its own H3.
- **Sort H3s by status order:** New → Fixed → Improved → Deprecated → Removed.
- **No separators:** no `---` / `***` / `<hr>` anywhere; headings do all the separation.
- **INCLUDE:** user-visible features, behaviour changes, bug fixes users would hit, deprecations/removals, migration notes. **EXCLUDE:** internal refactors, build/tooling, type fixes, and security-vector detail (a single neutral line is fine if a release is entirely internal). Map a technical `Changed` → `Improved`.
- **No intro paragraph** — go straight from H1 to the latest version's H2.

## HTML twin

Content-only fragment: start at `<h1>`, end at the last element. No
`<!doctype>`/`<html>`/`<head>`/`<body>`, no styles, no classes, no `<script>`,
no `<hr>`. Element map: `#`→`<h1>`, `##`→`<h2>`, `###`→`<h3>`, paragraph→`<p>`,
`**bold**`→`<strong>`, `` `code` ``→`<code>`, `[t](u)`→`<a href="u">t</a>`.
HTML-escape `<`, `>`, `&`, `"`.

## Notes

- These two files are publishing/dev artifacts — they are **not** shipped in the plugin zip (the `scripts/make-zips.mjs` allowlist excludes `CHANGELOG_*`).
- Run on every version bump; the project-local `/commit-version` calls this after bumping.
