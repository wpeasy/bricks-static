# Changelog

All notable changes to **Bricks Static** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-21

### Changed
- **Bricks Static is now split into a free plugin and a paid add-on.** This free plugin keeps the core feature set; advanced features move to **Bricks Static Pro** (sold separately). Pro features still appear in the dashboard but as disabled **"Requires Pro"** rows with an upgrade prompt, so nothing silently disappears.
- **Free now covers:** static generation (**up to 10 pages per sync**), **one** destination, **Text** replacements, per-file and package (zip) deploy, plain (uncompressed) files, `.htaccess` + nginx config, favicon, the single-page sync FAB, and CLI sync.
- **Moved to Pro:** unlimited pages, multiple destinations, Media/Links/Videos/Data-attribute replacements, gzip pre-compression, remote pruning, and sitemap/robots generation.
- If you previously configured **multiple destinations**, the extras are **hidden, not deleted** — they return if Bricks Static Pro is activated.

### Added
- The dashboard shows a **version badge** beside the title and, on the free version, a panel summarising the plan limits with an upgrade link. A sync that reaches the 10-page limit shows how to render the rest with Pro.
- An internal **edition/capability** layer and a deploy-pipeline seam so the Pro add-on can register its replacers, REST routes and sitemap generation without modifying this plugin.

### Fixed
- The single-page sync modal no longer lists destinations beyond the current plan's limit (they were shown but never actually synced).

## [0.0.3-beta] - 2026-06-14

### Added
- **Videos panel (type-aware).** A dedicated Videos section detects each video and offers the right control: local `<video>`/`<source>` files swap via the **WP media library** (the file is uploaded), while **YouTube/Vimeo/embeds** take a **URL or video ID** (matching Bricks — or paste embed code). YouTube gets a thumbnail; local video uses its poster. An embed's `origin=` (including the percent-encoded source domain) is **rewritten to each destination automatically** when a Destination URL is set. Video moved out of the Media panel (Media = images only).
- **Links panel.** Replace `<a>`/`<button>` `href` targets per destination — tag-scoped (body text is never touched).
- **Data attributes panel.** Replace the **value** of a `data-*` attribute per destination (the name is never changed), scoped to element tags only; framework-internal names and random ids are filtered from the list.
- Replacements are now a **single-open accordion** (Text · Media · Links · Videos · Data attributes), each with a count badge. Text replacements gained a modal editor and a richer WYSIWYG (headings, lists, HTML source view).
- **Documentation** admin submenu (features, how it works, common issues, troubleshooting, CLI).

### Changed
- "Only linked pages" is honoured strictly again — the crawl is no longer seeded from an all-published sitemap. The emitted `sitemap.xml` is now built from the pages actually exported, so it matches the discovery mode.
- Removed the per-destination "Include in single-page sync" setting — single-page sync now targets every enabled destination.
- The deploy only writes a per-destination page copy when the page actually changed.

### Fixed
- CSS background `url(&quot;…&quot;)` (entity-encoded quotes) and any `&amp;`-laden attribute/embed URL are parsed correctly — assets are collected/uploaded instead of producing broken 404 URLs.
- `wp_tempnam()` is no longer called on CLI/REST/front-end sync paths (it lives in wp-admin only and fatally errored there).

### Security
- See `SECURITY_PATTERNS.md`: SSRF guard (`Support\UrlSafety`) on the destination-URL fetch, SFTP host-key verification (trust-on-first-use), and TLS relaxation scoped to true dev hosts.

## [0.0.2-beta] - 2026-06-13

### Added
- **Replacements — Media & Links, in an accordion.** Alongside per-destination text replacements (now plain/rich with a small WYSIWYG incl. headings, lists and an HTML source view, edited in a modal): a **Media replacer** (swap any rendered image/video for a media-library item — rebuilding the new image's `srcset` variants so it stays responsive) and a **Link replacer** (rewrite `<a>`/button `href` targets — tag-scoped, so matching body text is never touched). Text/Media/Links are grouped in a one-open-at-a-time accordion. Stale media replacements (original no longer on the site) are pruned automatically.
- **Single-page sync + floating button.** A draggable "Sync this page" button (position persisted) on front-end pages and in the Bricks editor — where it **saves the editor first**. It renders the changed page and any **new pages it links to** (so you never publish a dead internal link), then pushes only the changed files to destinations opted into **Include in single-page sync**. Gated by an "Enable sync single button" toggle in the dashboard. REST `bs/v1/sync/page` + `/sync/page-status`.
- **Sitemaps, robots.txt & favicon.** A sitemap index + per-type sitemaps and a robots.txt are generated from discovered content and uploaded, with `<loc>`/`Sitemap:` URLs rewritten to each destination's domain; the sitemap also seeds discovery. A `/favicon.ico` is shipped from a real root favicon, the WordPress Site Icon, or a generated default.
- **Fast package deploy.** Zips the changed files + a one-shot, token-gated, self-deleting server-side helper, uploads both, and extracts over HTTPS — collapsing hundreds of FTP round trips into one, with automatic per-file fallback and a re-test action.
- **Documentation admin submenu.** A Svelte page (same styling) covering features, how it works, common issues & resolutions, troubleshooting, and a CLI reference.

### Changed
- "In sync" is now signature-based (render + that destination's replacements + URL), so a destination with replacements is no longer wrongly flagged "Out of date".
- A destination's **Check/Sync** buttons are disabled while it is not enabled.

### Fixed
- **gzip serving.** The `.htaccess` served pre-compressed `.gz` siblings for every type but only set the correct `Content-Encoding`/`Content-Type` for HTML/CSS/JS — so gzipped sitemaps/robots/SVG/JSON were delivered as undecodable blobs on Apache. Headers now cover every compressed type, and files under ~1 KB (e.g. robots.txt) are no longer gzipped.
- `wp_tempnam()` (wp-admin only) is no longer called on CLI/REST/front-end sync paths, where it fatally errored.
- The Progress card's dismissal now persists across reloads.

### Security
- **SSRF guard** (`Support\UrlSafety`) around the user-supplied destination-URL fetch: rejects private/reserved/link-local ranges (cloud metadata, RFC1918, IPv6 ULA/link-local), allows loopback for dev, and disables redirect-following.
- **SFTP host-key verification** (trust-on-first-use): the server key is read before login and a later mismatch is refused; cleared on connection-target change and via Reset.
- TLS-verification relaxation is scoped to true dev hosts via exact matching (fixing a `127.0.0.1`-prefix bug); `SECURITY_PATTERNS.md` rewritten for this plugin.

## [0.0.1-beta]

### Added
- **Multiple destinations.** Sync one source to many destinations. Each destination has its own connection, per-destination **literal text replacements** (applied only to visible text and `<img>` sources), **Enabled** and **Include in single-page sync** (TBA) switches, and its own pushed-state. The site is **rendered once** and deployed to each destination in turn. Dashboard: a **tabbed, 2-column** UI (add/remove destinations, common status above, an **All Destinations** tab to sync all sequentially or pick one). REST `bs/v1/destinations` CRUD + per-destination test; CLI `wp bricks-static sync --dest=<id> | --all`. The previous single connection migrates automatically to "Destination 1".
- Initial project scaffold.
- Base framework CSS (`assets/css/bs-framework.css`): fluid spacing and type scales, border tokens, and a light/dark admin color palette scoped to `.bs`.
- Plugin bootstrap (`bricks-static.php`) with a self-contained PSR-4 autoloader, `Plugin` bootstrap class, and a placeholder top-level admin page that enqueues the base framework CSS.
- **M1 — Connection & settings:** encrypted (AES-256-GCM) FTP/SFTP credential storage with wp-config constant overrides; transport layer (SFTP via bundled phpseclib, FTPS/explicit-TLS and plain FTP via ext-ftp) with runtime capability detection; REST API (`bs/v1`) for connection settings, connection testing, and status; staging-cache path resolver (`wp-content/cache/bricks-static/` with uploads fallback).
- Svelte 5 + Vite admin dashboard shell: Status, Method (shows the resolved discovery/transport/compression/target), and Connection panels.
- **M2 — Render & local cache (Check sync):** loopback page rendering (builder-agnostic, with a local-SSL escape hatch), URL discovery (published content + link crawl), origin→root-relative rewriting, comprehensive asset/link extraction (incl. lazy-load `data-src`/`data-srcset`/`data-bg` and CSS `url()`/`@import` backgrounds), gzip pre-compression, and a content manifest — driven by a resumable, batched job. Assets mirror their original WordPress paths (collision-free) and, where they exist on the source filesystem, are **listed for direct upload rather than copied into the cache** (only generated HTML + rewritten CSS are cached). REST `bs/v1/sync` (start/tick/status/cancel) plus dashboard **Actions** and live **Progress** panels.
- **M3 — Push (Sync):** full push over the configured transport — delta vs the last pushed manifest, batched upload streaming from source (gzip-on-the-fly for compressible source assets), with 3× retries for transient FTPS failures. A **"Currently synchronising" holding page** is shown at the destination home while a push runs and the real home is swapped in last (restored on cancel). The destination **`.htaccess` is fetched, merged** (our marker-delimited gzip-serving + cache rules; strips our old block and the conflicting WordPress block; backs up the original) and an **nginx snippet** is provided for manual paste. Real **"in sync"** status is computed from the render vs pushed manifests. Dashboard: enabled **Sync** button (with confirm), an upload progress bar, and a **Server config** panel.
- **M4 (partial) — Pruning & reset:** optional **prune** (per-run checkbox) deletes destination files (and their `.gz`) that no longer exist locally, as a batched phase after upload. **Reset sync state** clears the local push record so the next Sync re-uploads everything (for switching destinations or after a remote wipe).
- **Fix:** cancellation now reliably stops a run — the cancel flag is stored separately from the job so a concurrent tick can't overwrite it, and it's checked between items (not just per batch).
- **WP-CLI command** `wp bricks-static sync [--check] [--prune]` — renders/pushes from the command line. This is the reliable path on hosts that serialize PHP requests (e.g. Local on Windows), where the browser-driven loopback can't get a second worker. Browser-driven sync (curl/loopback) remains the automatic fallback on capable hosts.
- **Local detection + guidance:** the dashboard detects a local/dev environment and shows a notice steering you to the WP-CLI command, with an on-demand "Test browser rendering" check. Render loopback timeout raised to 60s for heavy archive pages.
- **Auto-run via WP-CLI:** when the host can spawn it (POSIX shell, `exec` enabled, `wp` on the web PATH — best-effort detected), the dashboard runs a sync through a detached `wp bricks-static run` process and just polls progress — no web-worker contention. Falls back to the browser-driven curl path otherwise. (Windows/Local can't spawn, so it uses the manual-command guidance.)
- **Fix:** opening the dashboard no longer risks locking up low-worker setups (e.g. Local). The status/method endpoints no longer make a loopback request (the cosmetic sitemap probe is removed), the dashboard loads its data sequentially, an interrupted run is no longer auto-resumed on page load, and a "running" job with no progress for 90s is auto-discarded.
