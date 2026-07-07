# Changelog

All notable changes to **Bricks Static** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-07-07

### Fixed
- **Dashboard crash on hosts that open wp-admin from a cross-origin tab (e.g. InstaWP).** The i18n dictionary lookup fell back to reading `window.opener.bsData`/`bspData`, which throws a `SecurityError` when the opener is a different origin — this could crash the entire dashboard on load. The fallback now fails safe instead.

## [1.0.2] - 2026-07-07

### Added
- **Media replacements are now a Free feature.** The Media panel (swap an image for another library item, scoped per page) has moved out of Pro — Free allows one swap per page; Pro removes the cap. Swaps are scoped per page with a page selector in the panel, group every responsive size variant under its source attachment (one swap covers the whole set, including lazy `data-src`/`data-srcset` images), and detect CSS background images set via inline `<style>`/`style=""` attributes alongside `<img>` elements. Images not in the media library fall back to an exact-URL swap, flagged in the panel.
- **Free now allows 2 destinations** (up from 1) before the Pro cap applies.
- **Concurrent destination deploys.** A new "Concurrent syncs" setting (1–10, default 2) lets a multi-destination sync upload to several destinations at once via detached WP-CLI worker processes, instead of one after another. Falls back automatically to the sequential per-destination path on hosts that can't spawn WP-CLI.
- **AI / MCP integration.** When the host exposes the WordPress Abilities API (WP 6.9+), the plugin registers abilities so an AI agent or MCP client can read status and operate sync. Two opt-in toggles (both off by default) gate it: **Allow changes** (set discovery mode, include/exclude pages) and **Allow sync** (scan, sync, single-page sync, cancel, reset). Read-only abilities (status, method, page list, link integrity, destinations, progress) are always available.
- **Manual discovery mode.** A third "Pages to include" mode that exports only the pages you choose: a per-post **Include** switch in the editor (metabox), the Pages/Posts list (new **Static** column, with bulk include/exclude), and the front-end Sync panel. Off by default except the static front page.
- **Pages overview + Process / View list.** A "Pages to include" control now renders the site on demand (**Process**) and, once current, shows **View list** — an Included / Excluded overview. Included pages carry compatibility notices (e.g. forms that post back to this site); excluded pages carry the reason (Not linked / Not included / Over plan limit / Not published).
- **Content-change tracking.** Post saves, status changes, Bricks meta writes and **menu edits** now flag the render stale: the **In sync** dot flips, and the control offers **Process** again. Published pages missing from the export surface as an "*N not in export*" chip that opens the Excluded tab.
- **Check as a no-render sync preview.** The per-destination **Check** button now diffs the existing render against what was last pushed and reports what a Sync would change — without re-rendering. If the render is stale/missing it points you to **Process** first.
- **Head cleaning.** Rendered pages drop WordPress-only `<head>` links (feeds, oEmbed, RSD, WLW, shortlink, `wp-json`) and emit a single `Bricks Sync … by BRXProd` generator tag. SEO/social meta (canonical, Open Graph, Twitter, robots) is preserved. Filterable via `bs_clean_head`.
- **Configurable timeouts.** New filters tune the loopback render (`bs_render_timeout`), dynamic-asset fetch (`bs_asset_timeout`, default raised 30→60s), remote package extract (`bs_package_timeout`), and the stalled-job watchdog (`bs_stale_seconds`) for slow hosts.

### Changed
- **Redesigned dashboard chrome.** The dashboard is rebuilt on the `ab-ui` component/theme library: a new gear-icon **Settings drawer** now holds discovery mode, the sync-button toggle, AI/MCP toggles and the new Concurrent syncs slider (moved out of the main toolbar), plus a light/dark/auto theme switcher, an accent-colour picker (with a custom colour option) and a compact-density switch.
- **Single-page sync is published-only.** The front-end button shows a notice on draft/pending pages instead of sync controls.
- **Custom dropdowns.** "Pages to include" and the transport selector use a custom dropdown so hover colours render correctly in the dark admin.

### Fixed
- **Stuck "Rendering Pages…" recovery.** Cancelling a run whose driver has gone away (e.g. the tab was refreshed during a WP-CLI prompt) now finalises immediately instead of leaving an un-closable progress card.
- **Package-deploy false failure on a no-op sync.** A package deploy with nothing changed and nothing to prune no longer attempts to build/upload an empty zip (which failed with a missing-file error); it now reports "Already up to date" instead.

## [1.0.1] - 2026-06-22

### Added
- **Translations.** The admin dashboard is now available in French, German, Italian, Spanish and Dutch — every label, button, tooltip, help text and status message is localised. WordPress loads the matching language automatically from your site language. (Implemented with a PHP string dictionary handed to the Svelte UI, so one set of translations covers both PHP and JS.)

## [1.0.0] - 2026-06-21

First public release. Bricks Static renders your Bricks-built pages to static HTML and deploys them to a remote host for performance. It ships as a free plugin with an optional **Bricks Static Pro** add-on for advanced features.

### Added
- **Static generation engine.** Loopback page rendering (builder-agnostic, with a local-SSL escape hatch), URL discovery (link crawl from the home page, or all published content), origin→root-relative rewriting, and comprehensive asset/link extraction (incl. lazy-load `data-src`/`data-srcset`/`data-bg` and CSS `url()`/`@import` backgrounds) — driven by a resumable, batched job with a content manifest and delta-aware pushes. Assets mirror their original WordPress paths and, where present on disk, are uploaded directly rather than copied into the cache.
- **One destination over FTP / SFTP / FTPS.** Encrypted (AES-256-GCM) credential storage with wp-config constant overrides; SFTP via bundled phpseclib, FTPS/explicit-TLS and plain FTP via ext-ftp, with runtime capability detection and a connection test.
- **Per-file and fast package deploy.** Per-file upload over the configured transport, or a fast package deploy that zips the changed files plus a one-shot, token-gated, self-deleting server-side helper and extracts over HTTPS — collapsing hundreds of FTP round trips into one, with automatic per-file fallback and a re-test action.
- **Text replacements.** Per-destination literal find/replace, applied only to visible text between tags (never attributes, scripts or markup), edited in a modal with a plain/rich WYSIWYG (headings, lists, HTML source view).
- **Single-page sync + floating button.** A draggable "Sync this page" button (position persisted) on front-end pages and in the Bricks editor — where it saves the editor first — that renders the changed page and any new pages it links to, then pushes only the changed files. Gated by an "Enable sync single button" toggle.
- **Server config + favicon.** The destination `.htaccess` is fetched and merged (marker-delimited cache rules; strips our old block and the conflicting WordPress block; backs up the original), with an nginx snippet provided for manual paste. A `/favicon.ico` is shipped from a real root favicon, the WordPress Site Icon, or a generated default.
- **WP-CLI** `wp bricks-static sync [--check]` — renders and pushes from the command line, the reliable path on hosts that serialize PHP requests (e.g. Local on Windows). Browser-driven sync (curl/loopback) is the automatic fallback on capable hosts, auto-spawning a detached WP-CLI process where supported and showing manual-command guidance otherwise. **Reset sync state** clears the local push record so the next Sync re-uploads everything.
- **Dashboard** (Svelte 5 + Vite): Status, Method (resolved discovery/transport/compression/target), Connection, Replacements and Server-config panels, a live Progress card (dismissal persists across reloads), and a Documentation submenu (features, how it works, common issues, troubleshooting, CLI reference).
- **Free / Pro split.** The free plugin covers the above with **one** destination and up to **10 pages** per sync. Advanced features — unlimited pages and destinations, Media / Links / Videos replacements, gzip pre-compression, remote pruning, and sitemap / robots.txt generation — are part of the optional **Bricks Static Pro** add-on, shown in the dashboard as disabled "Requires Pro" rows with an upgrade prompt. An internal edition/capability layer and a deploy-pipeline seam let Pro register its features without modifying this plugin. Destinations beyond the free cap are hidden, never deleted, and return if Pro is activated.

### Security
- **SSRF guard** (`Support\UrlSafety`) around the user-supplied destination-URL fetch: rejects private/reserved/link-local ranges, allows loopback for dev, and disables redirect-following.
- **SFTP host-key verification** (trust-on-first-use): the server key is read before login and a later mismatch is refused.
- TLS-verification relaxation is scoped to true dev hosts via exact matching. See `SECURITY_PATTERNS.md`.
