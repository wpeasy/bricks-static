# Changelog

All notable changes to **Bricks Static** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
