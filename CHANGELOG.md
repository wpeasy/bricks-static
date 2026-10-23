# Changelog

All notable changes to **ShipStatic** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.7] - 2026-10-23

### Changed
- **Clearer which destination you're editing.** With several destinations, edits meant for one were easy to make on another. The Replacements panel heading now names the destination ("Replacements for *Destination 2*", accent colour; `replacementsFor`); the selected destination tab is unmistakable (accent text, bold, accent bar — `.bs-tab--active` in `DestinationTabs.svelte`); and once a run has finished, the progress panel reads "Last sync · …" / "Last Process · …" instead of "Progress · …", since it can describe a different destination than the open tab (`ProgressPanel.svelte`).

### Fixed
- The progress heading lost the space before its "· destination" part ("Progress· …").

## [2.1.6] - 2026-10-23

### Added
- **Per-destination sub-paths — one source, any number of addresses.** The site is rendered once in a destination-neutral layout (render format 2): files at their plain paths, and every internal link carrying a placeholder (`Support\Url::BASE_TOKEN`, inserted by `Render\UrlRewriter` exactly where the source origin / home sub-path was). `Sync\Runner::build_deploy_manifest()` then gives each destination its own copy: its served path (from its Destination URL) filled into every page and stylesheet (`Url::rebase()`, after the replacers), sitemap/robots built for its address (`UrlRewriter::to_absolute(…, $served)`), and its files moved into its sub-path folder when it uploads to a web root (`file_prefix()` / `relocate()` — not for GitHub, whose repository root IS the served path, `TransportFactory::root_is_served_path()`; `robots.txt` and `favicon.ico` stay at the domain root). Two GitHub project sites, or a project site and a custom domain, now publish correctly from the same render — verified live with `wpeasy/shipstatic-pages-test` and `wpeasy/shipstatic-demo-2` (every internal link and asset under each site's own path, all 200), plus a text replacement on one destination only. Before, links followed the first destination's sub-path, so any other destination with a different one published pages without styles or images.
- `Runner::deploy_key($rel, $dest)` — a render path's key at a destination (used by the single-page status).

### Changed
- An existing render (format 1) is no longer current: press **Process** once after updating. The first sync per destination re-keys its push record (GitHub compares contents, so nothing re-uploads unchanged).
- Links panel + link replacements use plain paths (`/about/`), independent of any sub-path; a root-relative replacement target gets each destination's sub-path at deploy.
- The Test warning about a sub-path mismatch is removed — there is no longer anything to mismatch.
- `robots.txt`'s `Sitemap:` line now points where the sitemap really is for a sub-path site (it pointed at the domain root).

### Fixed
- Removing a destination now deletes everything kept for it (deploy manifest, sync signature, connection state, package-deploy flag, pool state, deploy copies) — before, these were left behind.

## [2.1.5] - 2026-10-23

### Changed
- **New ship mark.** The ShipStatic logo is now an old sailing ship in line art: the wordpress.org icon shows it sailing to the HTML5 logo (`.wordpress-org/icon.svg` → `icon-256x256.png` / `icon-128x128.png`); the admin menu uses a two-sail version in place of the generic `dashicons-media-code` (`Admin\Menu::MENU_ICON_SVG` — the strokes are drawn in a mask and painted through one filled rect, because WordPress's svg-painter recolours menu icons by rewriting `fill` only); and the dashboard header shows it before the title in the accent colour (`src-svelte/lib/icons/IconShip.svelte`, the start of the centralised icon module).
- readme: a Credits section for the HTML5 logo (W3C, CC BY 3.0); `screenshot-1.png` retaken at 2.1.4.

## [2.1.4] - 2026-10-23

### Changed
- **The sub-path now comes from the Destination URL.** `Destination::served_base_path()` returns the Destination URL's path whenever a URL is set (`https://owner.github.io/repo/` → `/repo/`); the "Served from sub-path" field applies only to a destination without a Destination URL (or when `SHIPSTATIC_BASE_PATH` pins it). `Support\Url::base_path()` reads it, so rendering, Export ZIP and the GitHub transport all follow. The dashboard shows the derived value read-only ("Taken from the Destination URL."). Before, the same path had to be typed twice, and a mismatch published pages without styles or images. The Test warning for the first destination now says to save the Destination URL; help text (GitHub Pages step 4, Export ZIP step 3, Docs page, README) updated.
- wordpress.org assets: new banners (ShipStatic, "Publish WordPress as a fast static site — to your host, Cloudflare or GitHub Pages.", current dashboard behind) and three real screenshots (dashboard, Images replacements, "Sync this page") replacing the old Bricks Static / Free-vs-Pro images and placeholders.

### Fixed
- **Images / Videos replacements with a sub-path:** every image showed "not in library" with a broken thumbnail, because the cached render's URLs carry the sub-path (`/repo/wp-content/…`) that the media library doesn't. New `Support\Url::to_source_path()` maps a rendered path back to the site path for attachment lookup and thumbnails (`Media\MediaCollector`, `Media\VideoCollector`). No change with the default sub-path `/`.

## [2.1.3] - 2026-10-23

### Changed — breaking (no installs yet, so no migration)
- **Renamed from Bricks Static to ShipStatic** — another plugin was released under the old name. Slug and text domain `shipstatic` (main file `shipstatic.php`, `languages/shipstatic-*.po/.mo`); prefixes `bricks_static_*` → `shipstatic_*` (options, transients, hooks/filters, error codes; post meta `_shipstatic_manual_include`), constants `BRICKS_STATIC_*` → `SHIPSTATIC_*` (e.g. `SHIPSTATIC_HOST`), REST namespace `shipstatic/v1`, script/style handles `shipstatic-*`, JS globals `shipStaticData` / `shipStaticEditorData` / `shipStaticFabData` / `shipStaticListData`, PHP namespace `WPEasy\ShipStatic`, WP-CLI `wp shipstatic`, Abilities category `shipstatic`, cache folder `wp-content/cache/shipstatic/`, generator meta `ShipStatic <ver> by BRXProd`. The "Why Bricks Static?" sections are now "Built for Bricks". CSS `.bs-` classes, DOM mount ids and the Connector receiver's internal names are unchanged. The GitHub repository is now `wpeasy/shipstatic` (the old `wpeasy/bricks-static` URL redirects).

### Added
- **GitHub Pages deploy method.** *Deploy via* gains **GitHub Pages**: owner, repository, branch (default `gh-pages`), folder (`/` or `/docs`) and a fine-grained token (Contents: Read and write). Each sync reads the branch (ref → commit → recursive tree), compares git blob SHAs computed locally, uploads only changed files — text inline in the create-tree request, binaries as blobs — then makes ONE commit and moves the branch fast-forward only (never forced). An unchanged site makes no commit; removed pages are deleted in the same commit. `.nojekyll` is always added; `CNAME`, README, LICENSE and `.github/` are never deleted (filter `shipstatic_github_keep_paths`); a `CNAME` is written for a custom-domain Destination URL when the branch has none. The export's "Served from sub-path" folder is published at the repository root (a project site's root IS `/<repo>/`). 25 MiB per-file cap (`shipstatic_github_max_file_bytes`); commit message filter `shipstatic_github_commit_message`. New: `Deploy\GitHubClient` (+ typed exceptions), `Transport\GitHubTransport`, Schema fields `ghOwner` / `ghRepo` / `ghBranch` / `ghFolder` / `ghToken` (encrypted; wp-config constants `SHIPSTATIC_GH_*`), WP-CLI `wp shipstatic github set|test|status`. Test checks read access, write access (one empty-blob probe — the repository's `permissions` field reports the account's role, not the token's) and the Pages status, and fills an empty Destination URL with the Pages address. "Check server" recognises GitHub Pages. Security model: SECURITY_PATTERNS.md §3d. Design + spike findings: `docs/GITHUB_PAGES_PLAN.md`.
- **Resumable snapshot publishes** (`Transport\ResumableSnapshotTransport`). GitHub allows ~500 writes an hour, so `Runner::tick_snapshot()` now runs a resumable transport one step per tick (state in `snapshotState`), pauses on a rate limit ("continues at …"; ticks only beat meanwhile, so the stale reaper never fires) and resumes. Recovers from a blob lost during a long pause (re-uploads once) and from a branch moved mid-sync (rebuilds on top of it once, keeping uploaded blobs). Cloudflare is unchanged (one tick).
- **Sub-path mismatch warning on Test** (every transport): when the Destination URL's path differs from the sub-path links are built for, Test says so and how to fix it.

### Fixed
- **"Served from sub-path" now prefixes links when WordPress is installed at the domain root.** Previously the files moved under the sub-path but every link and asset URL still pointed at the domain root, so the deployed site loaded without CSS, JS or images. `Render\UrlRewriter::rewrite()` now puts the base where the source origin was. (Sitemap/robots entries already carried it.) Sites using the default `/` are unaffected.

- Favicon generation no longer calls `imagedestroy()` (a no-op since PHP 8.0, deprecated in PHP 8.5).

### Changed
- Snapshot messages name the service ("Publishing to GitHub Pages failed: …"), and a snapshot transport's own summary (e.g. the commit made) becomes the sync's final message.

## [2.1.2] - 2026-10-23

### Changed — breaking (no installs yet, so no migration)
- **Prefix renamed** for wordpress.org's 4+ character rule: options, transients, hooks/filters and error codes `bs_*` → `bricks_static_*`; post meta `_bs_manual_include` → `_bricks_static_manual_include`; constants `BS_*` → `BRICKS_STATIC_*` (including the `wp-config.php` connection constants, e.g. `BRICKS_STATIC_HOST`); REST namespace `bs/v1` → `bricks-static/v1`; JS globals `bsData`/`bsEditorData`/`bsFabData`/`bsListData` → `bricksStatic…`; script/style handles `bs-*` → `bricks-static-*`; editor metabox id `bricks-static`. CSS classes (`.bs-`) and the Connector receiver's internal names are unchanged.

### Added
- `uninstall.php` now removes everything the plugin stores: options (including saved passwords and API tokens), transients, the per-post include meta, and the staging cache folder (only when it's the plugin's own `bricks-static` folder). Multisite-aware. Deployed files are never touched.
- Translations (fr_FR, de_DE, it_IT, es_ES, nl_NL) now cover the Cloudflare deploy method and the new plugin description — every UI string is translated again.
- readme, plugin description, README and the Docs page explain the name: Bricks Static is Bricks-aware (it ignores the random element IDs Bricks regenerates on every render, and renders through Bricks' maintenance mode), but works with all standard WordPress content.

### Changed
- Plugin author is now **BRXProd** (`https://brxprod.com`, no email); the obsolete alanblair.co Plugin URI is removed (the wordpress.org listing is the home page). `composer.json` authors/description updated, with `support` links to the wordpress.org forum and GitHub. readme: new "Where do I get support?" FAQ (wordpress.org forum). `WORDPRESS.md` header template updated to match.
- Plugin header: removed `Update URI: false`, which stopped WordPress from offering updates from wordpress.org.
- Exported pages' generator meta now reads `Bricks Static <ver> by BRXProd` (was the old name, `Bricks Sync`).
- Plugin header: removed `Network: false` (only `true` is valid); `Tested up to` 7.1.
- Generated files (the one-shot unzip helper, `.htaccess`, nginx snippet) now come from `*.txt` templates instead of PHP heredocs — output is byte-identical. wordpress.org's Plugin Check disallows heredocs.
- Files are deleted with `wp_delete_file()`; the dashboard's module script tag is built with `wp_get_script_tag()`; the Abilities category registration is guarded for WordPress < 6.9.
- The release zip now includes `composer.json`/`composer.lock` beside `vendor/`.
- Plugin Check: 0 errors (was 66); justified `phpcs:ignore` comments on the remaining necessary direct file/process calls.
- `readme.txt`: added an External services section (own servers, Cloudflare API, YouTube thumbnails), a source-code/build FAQ and a shell-commands FAQ; listed Cloudflare among the features.

## [2.1.1] - 2026-10-22

### Added
- **Cloudflare deploy method — publish to Cloudflare with no web host.** *Deploy via* gains a third choice, **Cloudflare**: enter an account ID, an API token (Account → Workers Scripts → Edit) and a Worker name, and each sync publishes the export as an assets-only Cloudflare Worker (Workers static assets — Cloudflare's current product for static sites). Every deploy is a **whole-site snapshot**: the plugin sends the full file list with a content hash per file, Cloudflare asks only for the files it doesn't already hold, those are uploaded in batches, and the new version goes live atomically — so there is no holding page, and a removed page disappears without a prune step. Cache headers are published as Cloudflare `_headers` rules (same policy as the `.htaccess`); Cloudflare compresses at the edge, so no `.htaccess` or `.gz` files are sent. `/about/` serves `about/index.html`, and the export's `404.html` is used for missing pages. Test validates the token and account and fills an empty Destination URL with the Worker's `workers.dev` address (enabled automatically unless the destination uses a custom domain). Files over Cloudflare's 25 MiB limit are left out and named in the result. New: `Transport\CloudflareTransport`, `Transport\SnapshotCapableTransport` (a new `snapshot` Runner phase), `Deploy\CloudflareClient`, `Deploy\CloudflareHeaders`; Schema fields `cfAccountId` / `cfWorker` / `cfApiToken` (encrypted; wp-config constants `BS_CF_ACCOUNT_ID` / `BS_CF_WORKER` / `BS_CF_API_TOKEN`); filters `bs_cloudflare_headers` / `bs_cloudflare_redirects`; WP-CLI `wp shipstatic cloudflare set|test|status`. "Check server" recognises Cloudflare destinations. Security model: SECURITY_PATTERNS.md §3c. Design + verification checklist: `docs/CLOUDFLARE_PLAN.md`.

### Security
- **Connector receiver (protocol v2) — re-pair existing Connector destinations.** From a security review:
  - **`.htaccess` files in subfolders are now checked** (HIGH). The `chunk` upload path only content-checked a root `.htaccess`, so one placed in a subfolder skipped the check, letting anyone holding the pairing secret register a server handler there — breaking the "a stolen secret can only deface, never run code" guarantee. Both write paths now check `.htaccess` at every depth and in any letter case, and a `.htaccess` that arrives without its content is refused.
  - **`.htaccess` is now allow-listed** (MEDIUM). Instead of refusing a list of known-dangerous directives, the receiver accepts only caching, rewrite, redirect and index/error-page directives (no rewrite handler/type flags; `Options` may only switch features off). Lines already in the destination's own `.htaccess` can still be written back, so merging into a host's existing file keeps working.
  - **Strangers can't hold a lockout** (LOW). The lockout after ten failed requests used to refuse everything, so anyone who knew the connector's URL could block deploys indefinitely. Correctly signed requests now still get through and clear it.
  - **Names ending in a dot or space are refused** (Windows strips them, which could turn `x.php.` into `x.php`).

  `ConnectorScript::VERSION` is now 2: a connector file from 2.1.0 answers "outdated — re-pair", so press **Re-pair (new file)** and upload the new file. SECURITY_PATTERNS.md §3b updated.
- **The Connector secret is never sent over plain HTTP** (LOW). HTTPS was required at pairing only; changing the Destination URL to `http://` afterwards made every request carry the secret in cleartext. `ConnectorClient` now refuses to send unless the URL is HTTPS (or a local dev host).
- **phpseclib 3.0.53 → 3.0.57** (bundled in `vendor/`), fixing CVE-2026-84308 (non-constant-time X25519, used in SFTP key exchange) and CVE-2026-55599 (X.509 AIA SSRF — not reachable from this plugin).

### Changed
- Places that special-cased the Connector as "the transport without an FTP host" now ask `TransportFactory::needs_host()` / `Destination::is_snapshot()`, so a new non-FTP transport doesn't need another special case.

## [2.1.0] - 2026-09-17

### Added
- **Connector (HTTPS) transport — deploy without an FTP account.** Each destination now has a *Deploy via* choice: **FTP / SFTP** or **Connector**. The Connector pairs the destination with a single PHP receiver (`bs-connector-<id>.php`) that you download from the dashboard and upload to the destination web root once; from then on every sync goes up as one chunked package over signed HTTPS (`Deploy\ConnectorScript` + `templates/connector.php.txt`, `Deploy\ConnectorClient`, `Transport\ConnectorTransport`, a new `PackageCapableTransport` branch in `PackageDeployer`). Security model (SECURITY_PATTERNS.md §3b): the file embeds only the *hash* of the pairing secret; every request carries an HMAC-SHA256 signature over id + timestamp + nonce + body hash, single-use nonces, a ±5-minute window and a lockout after ten failures; the receiver writes only inside its own folder (`realpath`-jailed, no symlink writes), refuses executable files (`.php` and friends, `x.php.txt`, `.user.ini`, `web.config`) and `.htaccess` lines that register handlers, and can read only `.htaccess` — so a leaked secret can at most deface the static folder, never run code. **Re-pair** rotates (the old file stops working), **Revoke** disconnects, **Rebuild push record** re-establishes what's already on the destination after a reset or re-pair so identical files aren't re-uploaded (files the plugin never pushed are never recorded, so prune leaves them alone). Test reports the receiver's PHP version, ZipArchive support and free space. REST: `POST /destinations/{id}/connector/pair|revoke|rebuild`, single-use `GET /connector/download?token=`. WP-CLI: `wp shipstatic connector pair|test|rebuild|revoke|status`. Needs PHP 7.4+ at the destination; ZipArchive there enables package deploys, otherwise files upload one by one. Spec: `docs/CONNECTOR_PLAN.md`.
- **"Check server" on the Destination Server Configuration tab** (`Sync\ServerProbe`, `GET /sync/server-check?dest=…`). Fetches the destination's home page and one pushed asset through the SSRF guard and reports the web server (Apache / LiteSpeed / nginx / CDN-proxied — or the exact origin via the Connector), whether HTML is gzip-served, both `Cache-Control` values and an applied / partial / not-applied verdict with a server-specific next step. This is the only way to confirm a manual nginx paste — or a working `.htaccess` — actually took effect. The last result per destination is kept and shown on reload.
- **Manual deploy section** on every destination: Export ZIP (moved off the toolbar) plus a collapsible how-to. Export no longer requires a tested connection — only an enabled destination — since nothing is uploaded.
- `UrlSafety::guarded_get()` (GET/HEAD) alongside `guarded_post()`, sharing one guard.

### Changed
- **The nginx snippet is now framed as optional tuning, not a setup step.** Stock nginx already serves the static output (`index.html` and directory resolution are defaults); the snippet only adds the cache policy and pre-compressed serving. The dashboard copy, snippet header and docs say so, and "Check server" verifies it.
- Destination URL and *Served from sub-path* moved above the deploy-method choice, since every method uses them.
- The `.htaccess` backup is only taken when the remote file isn't already our own generated block — previously a second sync could "back up" our own output, or clobber the genuine original saved by the first. `DirectoryIndex` now lists `index.php` after `index.html` as a safety net for destination roots that sit above a PHP app (Apache inherits `.htaccess` into subdirectories).
- Settings gear is twice its previous size.
- Translations regenerated: fr_FR, de_DE, it_IT, es_ES and nl_NL now cover every UI string (163 previously untranslated strings — connector, server check, manual deploy, setup wizard, settings, export, image replacer, sync diagnostics).

## [2.0.1] - 2026-08-05

### Fixed
- **Renders no longer capture the maintenance / coming-soon screen.** With Bricks' maintenance mode enabled, every page render mirrored the maintenance template instead of the real page — and because "coming soon" returns HTTP 200, the sync succeeded silently and published a site where every page was the holding screen. Renders now pass through the gate via a new `bricks/maintenance/should_apply` filter. The bypass is authenticated with a per-site shared secret (`X-BS-Render-Token`, backed by the non-autoloaded `bs_render_token` option and compared with `hash_equals()`), so only a render this site started gets through — copying the plugin's user agent is not enough, and a hidden site stays hidden from visitors. Disable with the `bs_bypass_maintenance` filter to capture the maintenance screen instead.
- **"Plain" permalinks are now caught before they wreck an export.** In that mode every page is a query string on the site root (`/?page_id=9`), so every page mapped to `index.html` and overwrote the home page. The dashboard now shows a blocking warning with a link to Permalink settings, the crawler skips the unmappable URLs instead of colliding on them, and the pages overview reports "Plain permalinks" as the exclusion reason rather than a misleading mode-based one.

### Changed
- **Skipped-page errors name the likely cause.** A non-200 render used to report a bare `HTTP 503`, which sent people hunting through server logs for a switch they had flipped themselves. 503, 403 and 401 now carry a hint — maintenance mode, a security plugin or firewall, or HTTP authentication.

## [2.0.0] - 2026-07-31

### Changed
- **ShipStatic Pro has been merged into the free plugin — every feature is now free, and the license requirement is gone.** Unlimited pages per sync, unlimited destinations, Links and Videos replacements (alongside Text and Images), gzip pre-compression, remote pruning, and sitemap/robots.txt generation are all built in with no cap and no upgrade prompts. The separate "ShipStatic Pro" plugin, its FluentCart license-key activation, and the internal edition/capability gating layer have all been removed.

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

First public release. ShipStatic renders your Bricks-built pages to static HTML and deploys them to a remote host for performance. It ships as a free plugin with an optional **ShipStatic Pro** add-on for advanced features.

### Added
- **Static generation engine.** Loopback page rendering (builder-agnostic, with a local-SSL escape hatch), URL discovery (link crawl from the home page, or all published content), origin→root-relative rewriting, and comprehensive asset/link extraction (incl. lazy-load `data-src`/`data-srcset`/`data-bg` and CSS `url()`/`@import` backgrounds) — driven by a resumable, batched job with a content manifest and delta-aware pushes. Assets mirror their original WordPress paths and, where present on disk, are uploaded directly rather than copied into the cache.
- **One destination over FTP / SFTP / FTPS.** Encrypted (AES-256-GCM) credential storage with wp-config constant overrides; SFTP via bundled phpseclib, FTPS/explicit-TLS and plain FTP via ext-ftp, with runtime capability detection and a connection test.
- **Per-file and fast package deploy.** Per-file upload over the configured transport, or a fast package deploy that zips the changed files plus a one-shot, token-gated, self-deleting server-side helper and extracts over HTTPS — collapsing hundreds of FTP round trips into one, with automatic per-file fallback and a re-test action.
- **Text replacements.** Per-destination literal find/replace, applied only to visible text between tags (never attributes, scripts or markup), edited in a modal with a plain/rich WYSIWYG (headings, lists, HTML source view).
- **Single-page sync + floating button.** A draggable "Sync this page" button (position persisted) on front-end pages and in the Bricks editor — where it saves the editor first — that renders the changed page and any new pages it links to, then pushes only the changed files. Gated by an "Enable sync single button" toggle.
- **Server config + favicon.** The destination `.htaccess` is fetched and merged (marker-delimited cache rules; strips our old block and the conflicting WordPress block; backs up the original), with an nginx snippet provided for manual paste. A `/favicon.ico` is shipped from a real root favicon, the WordPress Site Icon, or a generated default.
- **WP-CLI** `wp shipstatic sync [--check]` — renders and pushes from the command line, the reliable path on hosts that serialize PHP requests (e.g. Local on Windows). Browser-driven sync (curl/loopback) is the automatic fallback on capable hosts, auto-spawning a detached WP-CLI process where supported and showing manual-command guidance otherwise. **Reset sync state** clears the local push record so the next Sync re-uploads everything.
- **Dashboard** (Svelte 5 + Vite): Status, Method (resolved discovery/transport/compression/target), Connection, Replacements and Server-config panels, a live Progress card (dismissal persists across reloads), and a Documentation submenu (features, how it works, common issues, troubleshooting, CLI reference).
- **Free / Pro split.** The free plugin covers the above with **one** destination and up to **10 pages** per sync. Advanced features — unlimited pages and destinations, Media / Links / Videos replacements, gzip pre-compression, remote pruning, and sitemap / robots.txt generation — are part of the optional **ShipStatic Pro** add-on, shown in the dashboard as disabled "Requires Pro" rows with an upgrade prompt. An internal edition/capability layer and a deploy-pipeline seam let Pro register its features without modifying this plugin. Destinations beyond the free cap are hidden, never deleted, and return if Pro is activated.

### Security
- **SSRF guard** (`Support\UrlSafety`) around the user-supplied destination-URL fetch: rejects private/reserved/link-local ranges, allows loopback for dev, and disables redirect-following.
- **SFTP host-key verification** (trust-on-first-use): the server key is read before login and a later mismatch is refused.
- TLS-verification relaxation is scoped to true dev hosts via exact matching. See `SECURITY_PATTERNS.md`.

---

## Pro changelog (pre-merge history)

ShipStatic Pro shipped as a separate, licensed add-on plugin from 1.0.0 through 1.0.5 before its features were merged into this plugin in 2.0.0 (see above). Preserved here for historical record; these entries describe Pro-only releases and no longer correspond to a separate plugin.

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

First release as a standalone Pro add-on, split out of ShipStatic. Requires
ShipStatic (free) **1.0.0** or newer (`BSP_MIN_FREE`).

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
