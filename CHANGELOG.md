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
