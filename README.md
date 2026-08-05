# Bricks Static

Generate and serve static HTML versions of [Bricks](https://bricksbuilder.io/)-built pages for performance, then deploy them to any host over SFTP/FTPS/FTP.

> By [BRXProd](https://brxprod.com).

## Overview

**Bricks Static** renders your Bricks pages to static HTML (via authenticated loopback requests), rewrites their URLs to be portable, and pushes the result to one or more destinations — turning a dynamic WordPress + Bricks site into a fast, static front end while you keep editing in WordPress.

A single free plugin: unlimited pages and destinations, link/"all"/manual page discovery, text/media/link/video replacements, gzip pre-compression, remote pruning, sitemap generation, per-file or packaged deploy, `.htaccess` output, single-page sync, and an optional AI/MCP integration.

## Requirements

- **WordPress:** 6.5 or later (needs `wp_enqueue_script_module()` for ESM)
- **PHP:** 8.0 or later
- **Bricks** theme (the pages you export are Bricks-built)
- A destination reachable over **SFTP**, **FTPS**, or **FTP** (or use it purely to generate the local static cache)

## How it works

```
Discover URLs ─▶ Render (loopback) ─▶ Rewrite URLs ─▶ Cache ─▶ Deploy (per-destination) ─▶ Upload
   (mode)          PageRenderer          UrlRewriter    site/      build_deploy_manifest      transport
```

1. **Discover** the pages to export, based on the **discovery mode** (below).
2. **Render** each page with an authenticated loopback request, then clean the `<head>` (drop WordPress-only links, set a single generator tag) and rewrite URLs to root-relative.
3. **Cache** the result under `wp-content/cache/bricks-static/site/` and record a **render manifest**.
4. **Deploy**: for each destination, apply that destination’s replacements, build a deploy manifest, diff it against what was last pushed, and upload only what changed (as a single package + server-side unzip where possible, otherwise file-by-file).

The run is **batched and resumable**, driven by WP-CLI where available (best on low-worker hosts) and by the browser otherwise.

## The dashboard flow

**Pages to include → Process → Check → Sync.**

### 1. Pages to include (discovery mode)

| Mode | What’s exported |
|------|-----------------|
| **Only linked pages** (default) | Starts at the home page and follows internal links. Pages nothing links to (and builder/preview URLs) are left out. |
| **All published** | Every published page, post, and taxonomy archive — including orphaned/unlinked content. |
| **Manual** | Only pages whose per-post **Include** switch is on (off by default, except the static front page). Set it in the editor, the Pages/Posts list (the **Static** column), or the front-end Sync panel. |

### 2. Process (render)

**Process** renders the site for the current mode and refreshes the page list. It owns rendering — there is no auto-render on save. When the render is current the button becomes **View list**, opening the **Included / Excluded** overview:

- **Included** — pages in the export, with compatibility notices (e.g. “form submit action points to this site — won’t work statically”).
- **Excluded** — published pages not in the export, each tagged with the reason (**Not linked**, **Not included**, **Not published**).

A **content-change tracker** watches post saves, status changes, Bricks meta writes, and **menu edits**; any change flags the render stale, flips the **In sync** dot, and turns **View list** back into **Process**. If published pages exist that aren’t in the export, a “*N not in export*” chip appears next to the button and opens the Excluded tab.

### 3. Check (per destination, no render)

**Check** is a read-only **sync preview**: it diffs the current render against what was last pushed to that destination and reports what a Sync would change (files to upload / remove, or “up to date”). It does **not** render — if the render is stale or missing it tells you to **Process** first. It also flags any published pages missing from the export.

### 4. Sync (push)

**Sync** pushes the changed files to the destination, swapping in a holding page during the upload and the real home page last. Per-destination **status dots** show **Connected / Pushed / In sync**.

- **Single-page sync** — the front-end floating **Sync** button (and the editor) push one changed page (plus any new pages it links to) without a full rebuild. Published pages only.
- **Retry** re-uploads only the files that failed; **Reset** clears local push records so the next sync uploads everything.

## Panels

### Destinations

Add any number of destinations. Each has its own transport (**SFTP / FTPS / FTP**), credentials, remote path, destination URL, and a sub-path. **Test** validates the connection; the deploy badge shows whether the host supports **packaged deploy** (one zip + server-side unzip — fast) or falls back to **per-file** upload.

### Replacements (accordion)

Per-destination find-and-replace applied to the deploy copy, so different destinations can serve different content from one render:

- **Text** — literal/rich text swaps, scoped to text runs (never attributes/scripts/JSON-LD).
- **Media** — swap an image for a media-library attachment. Images are **grouped by source attachment**, so one swap covers every responsive size: the matched `<img>` (including lazy `data-src`/`data-srcset`) gets its `src`/`srcset` regenerated from the new image, intrinsic `width`/`height` updated, and CSS `url(...)` backgrounds rewritten too. Images not in the media library are swapped by exact URL and flagged.
- **Links** — rewrite link targets.
- **Videos** — swap a video/embed (and rewrite the embed origin to the destination).

### Server config

The generated **`.htaccess`** (uploaded automatically) and an **nginx** snippet for manual paste.

### AI tools (Abilities API / MCP)

When the host WordPress exposes the **Abilities API** (WP 6.9+), Bricks Static registers abilities so an AI agent / MCP client can operate it. Two opt-in toggles gate what’s allowed:

- **Read-only** (always available): get sync status, get sync method, list exportable pages, get page sync status, check page link integrity, list destinations, get sync progress.
- **Allow changes** (`aiAllowChanges`): set discovery mode, include/exclude a page, include multiple pages.
- **Allow sync** (`aiAllowSync`): scan (dry run), sync, sync a single page, cancel sync, reset sync state.

Both toggles default **off** — the AI can look but not touch until you opt in.

## Performance / timeouts

The configurable timeouts are **render / fetch ceilings during caching, not upload limits**:

- `bs_render_timeout` (60s) bounds each **page** loopback render; `bs_asset_timeout` (60s) bounds fetching a **dynamically-served** asset. Static files (uploads, theme CSS/JS) are read from disk, never fetched — so most assets aren’t subject to either.
- The **upload** itself is bundled: in package mode every changed file goes into **one zip** + a single server-side extract (`bs_package_timeout`, 120s); the per-file fallback is paced by transport (SFTP/FTP) timeouts. There is no per-asset upload ceiling.
- A `cURL error 28` therefore only comes from an HTTP call (page render, dynamic-asset fetch, or the package-extract POST) — never from an SFTP/FTP upload.

All of these are **filterable** for slow hosts (see Hooks). The sync **watchdog** that discards stalled jobs (`bs_stale_seconds`) is filterable too, so raising a request timeout above the default window doesn’t cause a still-working job to be reaped.

## REST API (`bs/v1`)

All routes require `manage_options`.

| Route | Purpose |
|-------|---------|
| `GET /status` | Dashboard status (connected, pushed, in-sync, render state, excluded count, AI flags). |
| `POST /settings` | Save discovery mode, FAB toggle, AI toggles. |
| `GET /pages-overview` | Included / Excluded page split with reason/notice tags. |
| `GET /connection`, `POST /connection`, `POST /connection/test` | Primary connection settings + test. |
| `GET/POST /destinations`, `POST/DELETE /destinations/{id}`, `…/test`, `…/package-test` | Manage destinations + probe deploy strategy. |
| `POST /sync/start` | Begin a `check` (render-only / Process) or `sync` (push) run. |
| `GET /sync/check?dest={id}` | Synchronous, no-render sync preview for a destination. |
| `POST /sync/tick`, `GET /sync`, `POST /sync/cancel`, `POST /sync/retry`, `POST /sync/claim` | Drive / poll / cancel / retry / claim the active run. |
| `POST /sync/page`, `GET /sync/page-status` | Single-page sync + its per-destination state. |
| `POST /sync/reset`, `POST /sync/preflight`, `GET /sync/server-config` | Reset push state, probe loopback, get `.htaccess`/nginx. |
| `POST /editor/include`, `…/include-bulk`, `GET /editor/links`, `GET /editor/inbound` | Manual-mode include toggles + link integrity. |
| `GET /media`, `GET /links`, `GET /videos` | Replacement catalogs from the last render. |
| `GET /sitemap/test` | Preview the generated sitemap. |

## Hooks

Selected filters/actions (prefix `bs_`):

**Extension seams**
- `bs_transport` — supply a custom transport implementation.

**Discovery / render**
- `bs_seed_urls`, `bs_excluded_post_types` — tune what’s discovered.
- `bs_render_request_args`, `bs_render_sslverify`, `bs_strip_emoji`, `bs_clean_head`, `bs_include_favicon` — tune rendering / head cleaning.
- `bs_render_timeout`, `bs_asset_timeout` — per-page / per-asset loopback timeouts (seconds).
- `bs_stale_seconds` — no-progress window before a stalled sync job is discarded.

**Deploy / transport**
- `bs_package_timeout`, `bs_package_sslverify` — remote unzip request.
- `bs_sftp_verify_host_key` — SFTP host-key trust policy.
- `bs_gzip_min_bytes`, `bs_cache_dir`, `bs_can_spawn_cli`, `bs_is_local`.

## WP-CLI

```bash
wp bricks-static sync            # full sync to the primary destination
wp bricks-static sync --check    # render only (no upload) — same as Process
wp bricks-static sync --all      # sync all enabled destinations
wp bricks-static sync --dest=ID  # a specific destination
wp bricks-static sync --prune    # also remove deleted files
```

Running WP-CLI against a Local by Flywheel site on Windows: see [`WP_CLI_LOCAL.md`](WP_CLI_LOCAL.md).

## Project properties

| Property | Value |
|----------|-------|
| PHP namespace | `WPEasy\BricksStatic` |
| Constants prefix | `BS_` |
| Textdomain | `bricks-static` |
| REST namespace | `bs/v1` |
| Options / table prefix | `bs_` |
| JS global | `window.BS` (data: `bsData`) |
| CSS prefix | `.bs-` |
| Static cache | `wp-content/cache/bricks-static/site/` |

## Development

Conventions and required reading:

- [`CLAUDE.md`](CLAUDE.md) — architecture, subsystems, i18n
- [`CODE_STANDARDS.md`](CODE_STANDARDS.md) — naming, security, PHP/JS/CSS standards
- [`SECURITY_PATTERNS.md`](SECURITY_PATTERNS.md) — admin-only REST, SSRF guard, TLS, transport hardening
- [`WORDPRESS.md`](WORDPRESS.md) — plugin header template
- [`SVELTE5_IMPLEMENTATION.md`](SVELTE5_IMPLEMENTATION.md) — Svelte 5 runes and patterns
- [`assets/css/bs-framework.css`](assets/css/bs-framework.css) — design tokens and base styles

Build:

```bash
npm run check    # TypeScript / Svelte type check
npm run build    # production build
npm run dev      # watch build
npm run zip      # stage + package the plugin zip (wp.org-safe allowlist)
```

## License

GPL-2.0-or-later
