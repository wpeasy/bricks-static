# Changelog

All notable changes to **Bricks Static** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-08-05

### Fixed
- **Renders no longer capture the maintenance / coming-soon screen.** With Bricks' maintenance mode enabled, every page render mirrored the maintenance template instead of the real page — and because "coming soon" returns HTTP 200, the sync succeeded silently and published a site where every page was the holding screen. Renders now pass through the gate via a new `bricks/maintenance/should_apply` filter. The bypass is authenticated with a per-site shared secret (`X-BS-Render-Token`, backed by the non-autoloaded `bs_render_token` option and compared with `hash_equals()`), so only a render this site started gets through — copying the plugin's user agent is not enough, and a hidden site stays hidden from visitors. Disable with the `bs_bypass_maintenance` filter to capture the maintenance screen instead.
- **"Plain" permalinks are now caught before they wreck an export.** In that mode every page is a query string on the site root (`/?page_id=9`), so every page mapped to `index.html` and overwrote the home page. The dashboard now shows a blocking warning with a link to Permalink settings, the crawler skips the unmappable URLs instead of colliding on them, and the pages overview reports "Plain permalinks" as the exclusion reason rather than a misleading mode-based one.

### Changed
- **Skipped-page errors name the likely cause.** A non-200 render used to report a bare `HTTP 503`, which sent people hunting through server logs for a switch they had flipped themselves. 503, 403 and 401 now carry a hint — maintenance mode, a security plugin or firewall, or HTTP authentication.

## [2.0.0] - 2026-07-31

### Changed
- **Bricks Static Pro has been merged into the free plugin — every feature is now free, and the license requirement is gone.** Unlimited pages per sync, unlimited destinations, Links and Videos replacements (alongside Text and Images), gzip pre-compression, remote pruning, and sitemap/robots.txt generation are all built in with no cap and no upgrade prompts. The separate "Bricks Static Pro" plugin, its FluentCart license-key activation, and the internal edition/capability gating layer have all been removed.

## [1.0.6] - 2026-07-11

### Added
- **Sync error details.** When a sync ends in error, its status badge is now clickable and opens a modal with the real failure reason(s) — per-destination messages for a multi-destination sync, plus the per-file error list. Previously only a generic "some destinations failed" summary was visible at a glance; a failing status badge is now visually distinct (red) from a plain cancellation (amber), which used to share the same colour.
- **"Pages with replacements" quick-jump.** The Images panel now shows a row of tags for every page that already has at least one saved swap (with its count), so you can see at a glance which pages need attention and jump straight to one instead of picking blind from the page selector.

### Changed
- **"Media" renamed to "Images"** throughout the dashboard (panel title, replacer labels, empty-state and cap-reached messages) — the feature only ever swaps images, so this removes ambiguity with document/file "media" in WordPress' own terminology.
- The Replacements accordion no longer shows an aggregate count badge for Images (or Pro's Videos) — the count is now visible per-page via the quick-jump tags above, which shows *where*, not just *how many*.
- A failed sync's summary message now names the destination(s) that actually failed (e.g. "Failed to deploy to Production — click the status badge for details") instead of a generic "some destinations failed to deploy."

### Fixed
- **"Served from sub-path" is now honoured.** The destination `basePath` setting (labelled *Served from sub-path (optional)*) was defined and shown in the dashboard but never consumed by the exporter, so a site installed under a sub-directory — e.g. a dev host that serves the site at `/mysite/` — always baked that prefix into the static output: files landed under `/mysite/` on the destination and the destination root returned a 404. The exporter now remaps the source home sub-path (the path of `home_url()`) to the configured base path in both the file layout and every internal URL. With the default base `/`, `/mysite/about/` becomes `/about/`, so the static copy serves correctly from the destination root; a base of `/blog` prefixes paths accordingly. The remap covers plain, JSON-escaped (`\/`) and HTML-entity-quoted (`&quot;`) URL forms, and is anchored so external URLs and same-prefix siblings (`/mysite-2`) are left untouched. It also applies to standalone URLs (attachment URLs, `srcset`, saved swap keys), not just document content, so the **Images** panel previews resolve and media swaps still match at deploy on a sub-directory install. Sitemaps and `robots.txt` (absolute URLs) are remapped too.

## [1.0.5] - 2026-07-10

### Added
- **Export ZIP.** A new "Export ZIP" button next to Check/Sync on each destination (and on the "All destinations" list) packages the CURRENT render into a downloadable zip — an alternative to FTP/SFTP Sync for hosts you deploy manually. Uses the destination's configured Media/Text replacements (same content a real Sync would push); never triggers a render itself (uses the same "Process first" prompt as Check when the render is stale/missing) and never touches that destination's sync/push stats, since nothing is actually uploaded. A progress panel shows preparing → creating gzip files → packaging → saving. Free zips include the plain files plus a cache-control `.htaccess`; Pro adds `.gz` files, `sitemap.xml` and `robots.txt`.
- **First-run setup wizard.** The dashboard now walks new installs through theme colour, pages-to-include, and enabling the single-page sync button, then runs Process automatically — no more landing on an empty dashboard. Re-run it anytime from a small "Wizard" button in Settings. Reactivating the plugin (e.g. after testing, or on a cloned site) always shows it again.

### Changed
- The "Reset sync state" button now sits directly beside its explanatory text instead of being split across opposite ends of the row.

### Fixed
- The Export ZIP "Download" button's text was invisible in some themes — a CSS specificity collision between the shared link-colour rule and the button's own colour painted the text the same shade as its background.

## [1.0.4] - 2026-07-07

### Fixed
- **Package deploy no longer retries a known-bad guessed helper URL forever.** When a destination has no explicit **Destination URL** set, the fast package deploy guesses one from the FTP/SFTP host (e.g. `https://ftp.example.com`) to call its one-shot deploy helper. That guess is often unreachable or fails TLS validation (e.g. `cURL error 60: SSL: no alternative certificate subject name matches...`), and previously this was retried — and failed the same way — on every single sync. It now disables package deploy for that destination after the first such failure (falling back to file-by-file, same as before) instead of repeating the error every sync. Setting an explicit Destination URL immediately re-enables it on the next sync.

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

---

## Pro changelog (pre-merge history)

Bricks Static Pro shipped as a separate, licensed add-on plugin from 1.0.0 through 1.0.5 before its features were merged into this plugin in 2.0.0 (see above). Preserved here for historical record; these entries describe Pro-only releases and no longer correspond to a separate plugin.

## [1.0.5] - 2026-07-11

### Added
- **"Pages with replacements" quick-jump** for the Video replacer, matching Free's new Images panel — a row of tags for every page with a saved video swap (with its count), to jump straight to one instead of picking blind.

### Changed
- **"Media replacer" renamed to "Image Replacer"** in the dictionary strings shared with Free's Images panel (labels, empty-state and "use this" wording) — no functional change.

## [1.0.4] - 2026-07-10

### Added
- **Export ZIP now includes gzip `.gz` siblings** and the fuller gzip-serving `.htaccess` rules, plus `sitemap.xml`/`robots.txt`, matching what a real Sync would push to a destination — the same content Free's new Export ZIP feature packages, with Pro's gzip pre-compression and sitemaps layered on. Falls back with an in-progress notice ("Gzip is not available on this server") if the PHP runtime lacks `gzencode()`.

### Changed
- No Pro-facing behaviour change, but bumped alongside Free: `src-svelte/lib/Modal.svelte` (shared with Pro's own Link/Video replacer panels) gained an optional `size` prop, used only by Free's new setup wizard — fully backward compatible with every existing Pro modal.

## [1.0.3] - 2026-07-07

### Fixed
- **Dashboard crash on hosts that open wp-admin from a cross-origin tab (e.g. InstaWP).** Same shared i18n fix as Free — the fallback that read `window.opener.bspData` could crash the dashboard when the opener was a different origin; it now fails safe.

## [1.0.2] - 2026-07-07

### Changed
- **Media replacement is no longer Pro-only.** The Media panel (including this release's CSS-background detection and responsive-set swapping) has moved to the free plugin, capped at one swap per page; Pro simply removes that per-page cap. Video replacements moved to the same per-page model at the same time (previously a single site-wide swap).

## [1.0.1] - 2026-06-22

### Added
- **"Only replacements" filter** on the Media, Links and Videos panels — off by default; when on, the list shows only items that already have a saved replacement.
- **Translations.** The Pro replacement panels (Media, Links, Videos) are now localised in French, German, Italian, Spanish and Dutch, matching the free plugin's dashboard language.

## [1.0.0] - 2026-06-21

First release as a standalone Pro add-on, split out of Bricks Static. Requires
Bricks Static (free) **1.0.0** or newer (`BSP_MIN_FREE`).

### Added
- **Unlimited pages** per sync — the free plugin renders up to 10 pages; Pro removes the cap.
- **Multiple destinations** — the free plugin is capped at one; Pro unlocks unlimited destinations and "sync all". Destinations beyond the free cap are preserved (never deleted) if the license lapses, and reappear when Pro is active again.
- **Advanced replacements** — Media, Links and Videos replacement panels, injected into the free dashboard's Replacements accordion. (Text replacements remain in Free.)
- **Gzip pre-compression** — `.gz` siblings plus the matching `.htaccess`/nginx serving rules. Free ships plain, uncompressed files.
- **Remote pruning** (`--prune`) — delete destination files that no longer exist locally.
- **Sitemap + robots.txt generation**, rewritten to each destination's origin.
- **FluentCart licensing** — license activation/management page, automatic updates, and a 7-day grace period. Pro features hard-gate off on expiry (saved settings are kept); an unlicensed install behaves like Free with an "enter license" notice.

### Notes
- Distributed as a separate plugin (Fluent Cart), installed alongside Free. WordPress's `Requires Plugins` header enforces the dependency.
