# Changelog

All notable changes to **Bricks Static Pro** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Bricks Static Pro is an add-on that requires the free **Bricks Static** plugin.
Its changelog is kept separate from Free's (`../CHANGELOG.md`); each release notes
only Pro-facing changes. Compatibility with Free is declared by `BSP_MIN_FREE`.

## [1.0.0] - 2026-06-21

First release as a standalone Pro add-on, split out of Bricks Static. Requires
Bricks Static (free) **1.0.0** or newer (`BSP_MIN_FREE`).

### Added
- **Unlimited pages** per sync — the free plugin renders up to 10 pages; Pro removes the cap.
- **Multiple destinations** — the free plugin is capped at one; Pro unlocks unlimited destinations and "sync all". Destinations beyond the free cap are preserved (never deleted) if the license lapses, and reappear when Pro is active again.
- **Advanced replacements** — Media, Links, Videos and Data-attribute replacement panels, injected into the free dashboard's Replacements accordion. (Text replacements remain in Free.)
- **Gzip pre-compression** — `.gz` siblings plus the matching `.htaccess`/nginx serving rules. Free ships plain, uncompressed files.
- **Remote pruning** (`--prune`) — delete destination files that no longer exist locally.
- **Sitemap + robots.txt generation**, rewritten to each destination's origin.
- **FluentCart licensing** — license activation/management page, automatic updates, and a 7-day grace period. Pro features hard-gate off on expiry (saved settings are kept); an unlicensed install behaves like Free with an "enter license" notice.

### Notes
- Distributed as a separate plugin (Fluent Cart), installed alongside Free. WordPress's `Requires Plugins` header enforces the dependency.
