# Changelog

All notable changes to **Bricks Static** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
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
- **Fix:** opening the dashboard no longer risks locking up low-worker setups (e.g. Local). The status/method endpoints no longer make a loopback request (the cosmetic sitemap probe is removed), the dashboard loads its data sequentially, an interrupted run is no longer auto-resumed on page load, and a "running" job with no progress for 90s is auto-discarded.
