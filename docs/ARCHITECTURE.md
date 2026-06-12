# Bricks Static — Architecture

> Status: design agreed; implementation in progress (M1).
> Bricks Static renders a WordPress site to static HTML/asset files and pushes
> them to a destination host over FTP/SFTP. It is **builder-agnostic** — it works
> with Bricks, Gutenberg, or anything else, because it renders the final HTML
> rather than reading builder internals.

## Context / why

Serving a static copy of a WordPress site from a destination host removes PHP and
DB load from the public site, improving performance and resilience. The source
WordPress install stays private/origin; the destination serves only flat files.

## Locked decisions

| Area | Choice |
|---|---|
| Rendering | Internal loopback HTTP crawl (`wp_remote_get` each URL → final HTML) |
| Discovery | WP published content + **sitemap if present, else crawl from homepage** following unique internal links |
| URL rewriting | Root-relative; sweep whole document + CSS; normalize http/https and www/non-www |
| Job execution | Browser-driven batched REST "tick" loop; resumable persisted queue |
| Sync strategy | Hash **manifest + delta** upload; "in sync" = current render manifest == last pushed manifest |
| Transport | SFTP (phpseclib, bundled) + FTP (ext-ftp); connection reused across a run |
| Credentials | Encrypted in `wp_options` (key derived from WP salts); **wp-config constants override** |
| Destination server | Generate `.htaccess` **and** an nginx snippet (target may be Apache/LiteSpeed or nginx) |
| Scale | Tuned for <100 URLs by default; queue + delta scale to thousands |
| Staging cache | `wp-content/cache/bricks-static/` (constant + filter overridable; auto-fallback to `uploads/` if not writable) |
| Durable state | Settings, encrypted creds, last-pushed manifest, job state → **DB** (purge-proof) |

## Storage layout

- **Staging cache** `wp-content/cache/bricks-static/` — rendered HTML, `.gz` siblings, mirrored assets (relative paths preserved), current-render manifest. Locked down with an `index.html` + deny drop-in; never the source of truth.
  - Overridable via `BS_CACHE_DIR` constant or a `bs_cache_dir` filter; falls back to `wp-content/uploads/bricks-static/` if `wp-content/` is not writable.
- **Durable (DB):**
  - `bs_settings` option — destination config + encrypted credentials (constants override at read time).
  - `bs_pushed_manifest` — hashes of what was last successfully pushed (drives "in sync").
  - `{$wpdb->prefix}bs_jobs` table (large sites) / option (small) — job phase, counts, per-item status, errors, for resumability.

## PHP structure (`WPEasy\BricksStatic\…`, PSR-4 from `src/`)

```
src/
├── Plugin.php                # (exists) bootstrap
├── Admin/Menu.php            # (exists) dashboard page + asset enqueue
├── REST/
│   ├── ConnectionController  # POST test, POST save, GET status
│   └── SyncController        # POST start, POST tick (batch), GET status, POST cancel
├── Discovery/
│   ├── UrlCollector          # WP published content + sitemap detection
│   └── Crawler               # homepage link-crawl fallback + per-page link harvest
├── Render/
│   ├── PageRenderer          # loopback fetch → HTML (+ internal base URL / auth escape hatch)
│   ├── UrlRewriter           # absolute source origin → root-relative (HTML + CSS)
│   └── AssetExtractor        # src/href/srcset, CSS url()/@import discovery
├── Transport/
│   ├── TransportInterface    # connect, put, mkdir, exists, listing, disconnect
│   ├── SftpTransport         # phpseclib
│   └── FtpTransport          # ext-ftp
├── Sync/
│   ├── Job                   # persisted job + queue state
│   ├── Manifest              # per-file content hashes; delta diff
│   ├── Compressor            # gzencode .gz siblings (if zlib)
│   └── HtaccessBuilder       # .htaccess + nginx snippet
├── Settings/
│   ├── Settings              # typed read/write of bs_settings (+ constant override)
│   └── Schema                # field definitions / sanitisation
└── Support/
    ├── Crypto                # encrypt/decrypt creds via WP salts
    ├── Lock                  # prevent concurrent syncs
    └── Paths                 # cache dir resolution, URL→file mapping
```

## Sync pipeline (one run, executed as resumable batches)

1. **Resolve method** — detect sitemap, zlib/gzip, transport, server target; record on the job (surfaced in the UI).
2. **Collect URLs** — WP content + sitemap; if no sitemap, seed the homepage. Enqueue.
3. **Per page (batched)** — loopback render → harvest internal links (enqueue new unique) → rewrite origin → root-relative → extract assets → write `…/<relative>/index.html` → write `.gz` sibling if zlib.
4. **Assets (batched)** — fetch each unique same-origin asset → store under the same relative path → parse CSS for nested `url()`.
5. **Manifest diff** — hash every output, compare to `bs_pushed_manifest` → compute the new/changed upload set.
6. **Upload (batched)** — push changed files + `.gz` over the chosen transport, preserving paths.
7. **`.htaccess`** — back up any existing one, then write: serve `.gz` when present; cache-control HTML short/revalidate, static assets long + `immutable`. Emit an nginx snippet for manual paste.
8. **Finalize** — store the pushed manifest; status → *In sync*.

## Admin dashboard (Svelte 5, `.bs` framework, REST `bs/v1`)

- **Connection** — transport (SFTP/FTP), host/port/user/remote path, *Test connection*; indicates whether creds come from DB or wp-config constants.
- **Status** — three indicators: *Connected* · *Pushed yet?* · *In sync*.
- **Method panel** — resolved live: `Discovery: Sitemap (N URLs)` or `Crawl from homepage`; `Transport: SFTP`; `Target: .htaccess + nginx snippet`; `Compression: gzip ✓/✗`; `Links: root-relative`.
- **Actions** — *Check sync* (dry run: discover + render + diff, no upload; reports the delta) and *Sync* (full run).
- **Progress** — live phase, counts, current item, error list; polled from the tick loop; resumable.

## Milestones

- **M1 — Connection & settings:** transport classes, encrypted creds + constant override, *Test connection*, status scaffolding, dashboard shell + Method panel.
- **M2 — Render & local cache:** discovery, loopback render, rewrite, asset extraction, gzip → staged files; *Check sync* dry run + progress UI.
- **M3 — Push:** manifest/delta, batched upload, `.htaccess`/nginx, full *Sync* + in-sync status.
- **M4 (later):** prune unused destination files; per-page "update this page" button in Gutenberg/Bricks editors; destination PHP agent for true remote verification.

## Known roadblocks / risks (mitigations baked into the design)

- **Loopback may fail** (host blocks self-requests, Cloudflare/page-cache/HTTP-auth/staging password) → internal base URL + auth-header override + preflight check.
- **Pretty permalinks required** — plain `?p=` URLs have no clean file mapping → detect + warn.
- **Dynamic features break statically** (forms, search, comments, login, nonces, `/wp-json`, `admin-ajax`) → documented out of scope; surfaced in UI.
- **Asset discovery gaps** — JS-constructed URLs are undetectable statically; CSS `url()`/`@import`/`srcset` are parsed → report unresolved references.
- **Origin strings hide in inline JS/JSON-LD/inline styles/data-attrs** → sweep whole document + CSS, carefully, normalizing scheme + www.
- **CDN/optimization plugins** push asset URLs off-origin → left as-is + warned (not mirrored).
- **SFTP support** — `ext-ssh2` usually absent → bundle phpseclib; FTP is slow for many small files (per-file round trips) → reuse connection, delta upload.
- **Credential security** — encryption key sits on the same server → constants path for the security-conscious.
- **"In sync" unverifiable** without the destination agent → compares local render vs last push only; direct destination edits can drift (resolved in M4).
- **Overwriting destination `.htaccess`** → back up / never blind-overwrite.
- **Browser-tab model** — closing the tab pauses a run → resumable job state + clear messaging.
- **Subdirectory destinations** break root-relative URLs → "destination base path" setting.
