# Cloudflare transport — implementation plan

> Status: **implemented** (2026-10-22, v2.1.1). Verified against a mocked
> Cloudflare API (see §12); **not yet run against a live Cloudflare account** —
> do that before release using the checklist in §10.
> Where the build differs from the original plan text, §12 says so.
> A fifth deploy method that publishes the export to Cloudflare's network
> through the Cloudflare API, with no FTP account and no PHP on the
> destination. Read `SECURITY_PATTERNS.md` (the TLS/`sslverify` rule and secret
> storage) before touching the HTTP client.

## 1. Why

Many users want static hosting without a PHP host at all. Cloudflare serves
static files for free on every plan, from its global network, with automatic
gzip/brotli, HTTPS and custom domains. The connector plan already lists
"non-PHP targets (Netlify, S3, Cloudflare Pages)" as out of scope for the
connector; this is the first of those.

## 2. Pages vs Workers — which product to target

Both serve a folder of static files equally fast and free. They differ in how
a tool like ours uploads:

| | Pages (Direct Upload) | Workers static assets |
|---|---|---|
| Upload API | Only partly documented (API reference + Cloudflare's CLI source) | Documented step by step |
| File key | `blake3(base64(bytes) + ext)[:32]` — PHP has no BLAKE3 (`hash_algos()` on PHP 8.5: absent; sodium's `generichash` is BLAKE2b, not compatible) | SHA-256 in Cloudflare's own example — native `hash('sha256', …)` |
| Wrong key | Accepted silently, every file 404s | Same failure mode, far less likely |
| Direction | Maintained, little new work | Where Cloudflare's new features go |

**Decision: build on Workers static assets.** The user-facing option is labelled
"Cloudflare"; the user gets the same result either way (their site on
`*.workers.dev` or their own domain). Revisit Pages only if users specifically
need an existing Pages project updated — that would mean a pure-PHP BLAKE3
(checked against the official test vectors) behind the same transport.

**Resolved — no script needed.** wrangler's upload-form builder
(`create-worker-upload-form.ts`) short-circuits an assets-only Worker to a
form with just a `metadata` part (`{assets: {jwt, config}, compatibility_date}`),
so we do the same.

## 3. Naming

- Transport id: `cloudflare` (5th `Schema` `transport` enum value; label
  "Cloudflare").
- PHP: `Transport\CloudflareTransport`, `Deploy\CloudflareClient` (HTTP
  protocol client), `Deploy\CloudflareHeaders` (builds `_headers` /
  `_redirects`).
- Worker script name on Cloudflare = the user's "project name" field.

## 4. Cloudflare API flow (Workers static assets)

Base: `https://api.cloudflare.com/client/v4`. Auth: `Authorization: Bearer
<API token>` except step 2, which uses the upload JWT.

1. **Start an upload session.**
   `POST /accounts/{account}/workers/scripts/{name}/assets-upload-session`
   with `{"manifest": {"/about/index.html": {"hash": "<32 hex>", "size": 1234}, …}}`
   covering the **full** deploy set. Response: `jwt` (valid 1 hour) and
   `buckets` (arrays of hashes Cloudflare doesn't already have). Empty
   `buckets` ⇒ the `jwt` is already a completion token; skip to step 3.
2. **Upload missing files**, one request per bucket.
   `POST /accounts/{account}/workers/assets/upload?base64=true`,
   `Authorization: Bearer <upload jwt>`, `multipart/form-data`, one part per
   file named by its hash, base64 body, part `Content-Type` = the MIME type to
   serve it with. The final bucket's 201 response carries the completion `jwt`.
3. **Publish.** `PUT /accounts/{account}/workers/scripts/{name}` (multipart:
   `metadata` with `assets.jwt` = completion token, `assets.config` with
   `html_handling` / `not_found_handling: "404-page"`, plus the script module
   if §2's question says one is needed). The new version goes live
   atomically.

**File key:** `substr(hash('sha256', base64_encode($bytes) . $ext), 0, 32)`,
where `$ext` is the extension without the dot — the formula in Cloudflare's
own TypeScript example (`examples/workers/script-with-assets-upload.ts`).
wrangler computes BLAKE3 over the same input instead, which shows the key is an
opaque content address chosen by the client, not something Cloudflare
recomputes. We stream the hash (`CloudflareTransport::hash_file()`, base64 in
3-byte-aligned chunks) so a 25 MiB file isn't held in memory twice.

**Upload format** (from wrangler's `deploy-helpers/assets.ts`): one
`multipart/form-data` request per bucket; each part is named by the hash, with
`filename` = the hash, `Content-Type` = the MIME type to serve
(`application/null` = none), body = base64 of the file.

**`_headers` / `_redirects`** go in the publish metadata as strings
(`assets.config._headers`), not as uploaded files — again matching wrangler.

**Gotchas carried over from Pages experience:** manifest keys need a leading
`/`; a wrong key is accepted and then 404s; poll/verify after publish rather
than trusting a 200.

## 5. What changes in the sync flow

Every existing transport deploys a **delta** (`Runner::start_target` →
`Manifest::diff(pushed, deploy)` → upload `changed`, prune `removed`).
Cloudflare deploys a **complete snapshot** every time, with server-side
de-duplication doing the delta work.

- **New interface `Transport\SnapshotCapableTransport`** (alongside
  `PackageCapableTransport`):
  `deploy_snapshot(array $files, ?callable $progress = null): array` where
  `$files` is export-relative path => local file for the FULL deploy set,
  returning `{ok, retryable, uploaded, skipped, errors, message}`.
- **`Runner::start_target`** branches on it *before* the package/upload choice:
  a new `snapshot` phase that loads the full deploy manifest
  (`Manifest::load(deploy_option)`), not `queue.uploads`.
- **Skipped for snapshot destinations:** `upload_htaccess`, the holding
  `index.html` / home-last swap, `restore_home`, `tick_prune` (a removed page
  simply isn't in the next snapshot), `upload_gz` and `.gz` siblings
  (Cloudflare compresses itself), `PackageDeployer` / `UnzipScript`.
- **Pushed manifest** = the full deploy manifest on success (as the package
  path already does), so `in_sync()`, Check and `sync_signature()` work
  unchanged.
- **Our hashes are not Cloudflare's.** `StableHash` is md5 of *normalised*
  HTML; Cloudflare's key is computed from the exact deploy bytes inside the
  transport. (Built without a key cache — hashing is a streamed SHA-256 per
  file each sync. Add a per-(path, md5) cache only if large sites show it
  matters.)
- **Ticks:** like the package phase, the whole publish runs in ONE tick, with
  the progress callback calling `beat()` + saving the stage message so the
  watchdog and the dashboard stay live. The session `jwt` expires after an
  hour; on a 401 during upload the transport starts a new session once
  (cheap — already-uploaded files drop out of `buckets`).
- **Single-page sync** still publishes a full snapshot (cheap because of
  de-duplication), so it creates a version in the Worker's history each time.
- **`DeployPool`** (concurrent multi-destination) works unchanged — it runs
  the same phase switch.

## 6. `_headers` / `_redirects` instead of `.htaccess`

`HtaccessBuilder` today emits cache rules for Apache. For Cloudflare,
`Deploy\CloudflareHeaders` emits the equivalent into a `_headers` file (cache
lifetimes per file type) and any redirects into `_redirects`, both added to
the snapshot as ordinary files. Limits: `_headers` 100 rules, 2,000 characters
per header; `_redirects` 2,000 static + 100 dynamic. Group rules by pattern
(`/*.css`, `/*.js`, `/wp-content/uploads/*`) so we stay far below them.

## 7. Source-side integration

### 7.1 Settings / Schema
- `transport` enum gains `cloudflare` (`Schema.php:39`; sanitising at :89
  falls back to the default for unknown values, so this is required).
- New fields: `cfAccountId`, `cfProjectName`, `cfApiToken` (`secret => true`,
  `Crypto`-encrypted, shown via `for_display()` as `{hasValue, fromConstant}`).
  Add them to `Destination::connection_config()` (:104).
- `destinationUrl` stays required — the `*.workers.dev` address or the custom
  domain; sitemap/robots/replacement rewriting already use it.
- `host`/`port`/`username`/`password`/`remotePath` are unused for this
  transport.

### 7.2 Generalise the connector special cases
These check for `connector` specifically and must become "transport has no
FTP host" rather than gaining a second special case:
`Destination::is_connector()` (Destination.php:124), destination identity
signature (Destinations.php:198-211 — for Cloudflare: account id + project
name), host-required check (DestinationsController.php:377), labels in
`MethodPanel.svelte:20`.

### 7.3 `Deploy\CloudflareClient`
- Fixed host `api.cloudflare.com` only (never user-supplied), so the
  `UrlSafety` SSRF guard isn't needed — but `sslverify` stays on per
  `SECURITY_PATTERNS.md`.
- Needs PUT and multipart. Add a public generic request method next to
  `UrlSafety::guarded_get` / `guarded_post` (their private helper already
  supports any method) rather than calling `wp_remote_request` ad hoc.
- Retries with backoff on 429 / 5xx; surface Cloudflare's `errors[].message`.
- Never log or return the API token or JWTs.

### 7.4 Test connection & server check
- `POST /destinations/{id}/test` → `GET /accounts/{id}/workers/subdomain`, a
  read-only call that fails on a bad token, a wrong account id or a token
  without Workers access, and also yields the account's workers.dev
  subdomain. The response carries `suggestedUrl`
  (`https://<worker>.<sub>.workers.dev`), which the panel puts into an empty
  Destination URL. The Worker itself is created on first sync.
- `ServerProbe` assumes Apache/nginx (`Server` header classification at
  :290). Add a Cloudflare branch: expect `server: cloudflare`, report
  compression + cache headers, skip the `.htaccess`/nginx advice.

### 7.5 Dashboard (`DestinationPanel.svelte`)
- `Segmented` method choice gains "Cloudflare" (`methodOptions` :55).
- Field block: account id, project name, API token (password-style input with
  the existing "saved" / clear behaviour), plus a short how-to: create a token
  with **Account → Workers Scripts → Edit**, find the account id, point a
  custom domain at the Worker in the Cloudflare dashboard.
- Hide the Manual deploy section's server-specific advice; Export ZIP itself
  stays available.
- `Transport` union + capabilities in `shared/types.ts`; strings in
  `Support\I18n`.

### 7.6 WP-CLI
`wp shipstatic cloudflare set|test|status` — `set` takes `--account`,
`--worker`, `--dest-url` and `--token-from-env` (reads `CLOUDFLARE_API_TOKEN`,
keeping the token out of shell history); `status` lists Cloudflare
destinations (worker, account, whether a token is set, URL).

### 7.7 Abilities API
No new abilities — sync/status abilities already go through `Runner` and pick
the transport up automatically.

## 8. Phases

1. **Spike** — a WP-CLI command that publishes a small export end to end:
   settles the file-key formula, the upload multipart format and whether a
   script is required. Throwaway code; findings go back into this doc.
2. **Transport + Runner** — `SnapshotCapableTransport`,
   `CloudflareTransport`, `CloudflareClient`, the `snapshot` phase and the
   skips in §5, Schema fields, generalised special cases.
3. **Polish** — `_headers` / `_redirects`, dashboard fields + how-to,
   connection test, `ServerProbe` branch, WP-CLI, i18n, docs page section,
   CHANGELOG.

## 9. Limitations to document for users

- **Files:** 20,000 per site on Free, 100,000 on paid plans (Pages figures;
  confirm the Workers static assets equivalent).
- **File size:** 25 MiB per file — large exported videos fail; host them on
  R2 / YouTube / Vimeo instead (the Videos replacement can point at them).
- **No PHP on the destination:** the Connector and package-deploy don't apply;
  forms posting back to WordPress don't work (already true for any static
  host).
- **`_headers` / `_redirects` caps** as in §6.
- **Every sync (including single-page) is a new version** in the Worker's
  history.
- **Custom domains** are attached in the Cloudflare dashboard, not by the
  plugin (v1).

## 10. Verification checklist

- Fresh project: first sync creates the Worker, all pages and assets 200,
  `/about/` serves `about/index.html`, unknown path serves `404.html`.
- Second sync with no changes: empty `buckets`, nothing uploaded, reported in
  sync.
- Edit one page: exactly one file uploaded.
- Exclude a page: it 404s after the next sync (no prune step).
- Media/Text/Links/Videos replacements show up in the published copy.
- `_headers` cache rules visible in response headers.
- Bad token / wrong account id: clear error from Test and from Sync, no token
  in logs or REST responses.
- JWT expiry mid-upload: sync recovers.
- Concurrent sync with an FTP destination via `DeployPool`.

## 11. Open questions

- ~~Assets-only Worker without a script (§2)?~~ Resolved: metadata-only form.
- ~~Exact file-key formula (§4)?~~ Resolved: Cloudflare's example formula.
- ~~Create the Worker on first sync, or require it to exist?~~ Created
  automatically by the publish `PUT`.
- Should we also support updating an *existing* Pages project (BLAKE3 path),
  or document migrating it to Workers? (Currently: not supported.)
- Attach a custom domain via the API in a later version? (Currently: done by
  the user in the Cloudflare dashboard; documented on the Docs page.)
- Very large buckets: the multipart body for one bucket is built in memory
  (Cloudflare sizes buckets up to ~50 MiB of files, ~67 MiB base64). Fine for
  typical hosts; if `memory_limit` proves a problem, switch big files to the
  single-asset endpoint wrangler also uses
  (`POST …/workers/assets/upload/{hash}` with a raw body).

## 12. Implementation notes (as built)

**Files**

| File | Role |
|---|---|
| `src/Transport/SnapshotCapableTransport.php` | The snapshot contract (§5). |
| `src/Transport/CloudflareTransport.php` | Hashing, manifest, buckets, publish, workers.dev, MIME map, 25 MiB rule. Per-file `TransportInterface` methods are inert (`put()` throws). |
| `src/Deploy/CloudflareClient.php` | The four API calls + envelope parsing, retries (transport error / 429 with `Retry-After` / 5xx, 3 tries), 401/403 → `CloudflareAuthException`, 404 → `CloudflareNotFoundException`. |
| `src/Deploy/CloudflareHeaders.php` | `_headers` (one `/*.ext` immutable rule per `HtaccessBuilder::asset_extensions()` entry; no `/*` catch-all, because Cloudflare joins duplicate headers and its default is already our HTML policy) and `_redirects` (empty, filterable). |
| `src/Sync/Runner.php` | `start_target()` → `snapshot` phase; `tick_snapshot()`; also handled in `deploy_target_worker()` (parallel pool); `done_message()` names files left out. |
| `src/Settings/Schema.php`, `Destination.php`, `Destinations.php` | `cfAccountId` / `cfWorker` / `cfApiToken`, `is_cloudflare()`, `is_snapshot()`, identity = account + worker. |
| `src/Transport/TransportFactory.php` | `cloudflare` case, `capabilities()['cloudflare']`, `needs_host()`, `is_snapshot()`. |
| `src/Deploy/PackageDeployer.php` | `would_package()` is false for snapshot destinations. |
| `src/REST/DestinationsController.php` | Test merges unsaved Cloudflare fields, returns `suggestedUrl`; package-test refuses snapshot destinations. |
| `src/Sync/ServerProbe.php`, `MethodResolver.php`, `REST/SyncController.php` | Cloudflare server kind + message; method summary shows `_headers`; server-config payload carries `cloudflareHeaders`. |
| `src/CLI/SyncCommand.php` | `wp shipstatic cloudflare …`. |
| `src-svelte/dashboard/DestinationPanel.svelte` | Third Segmented option, fields, how-to, URL auto-fill. Also `MethodPanel`, `ProgressPanel` (indeterminate bar for `snapshot`), `ServerConfigPanel` (`_headers` reference), `shared/types.ts`, `docs/Docs.svelte` (user guide). |
| `src/Support/I18n.php` | New `opt/cf*` strings — English only until the i18n pipeline is re-run (CLAUDE.md → i18n). |

**Differences from the plan text above:** no hash cache (§5); the publish runs
in one tick rather than buckets across ticks (§5); the connection test uses
the workers.dev subdomain endpoint rather than `tokens/verify` (§7.4); the CLI
gained `set` (§7.6). The connector special cases were generalised to
`TransportFactory::needs_host()` / `Destination::is_snapshot()` where a check
meant "no FTP host"; Connector-only features (pair/rebuild) still check
`is_connector()`, correctly.

**Verification so far** — a `wp eval-file` smoke test with `pre_http_request`
mocking `api.cloudflare.com` (34 checks, all passing): Schema sanitising
(account id, Worker name, enum); streamed hash equals the whole-file formula
across chunk boundaries; `_headers` shape; factory/flags/`needs_host`;
`would_package` off; connect errors without credentials; workers.dev URL;
end-to-end deploy (manifest keys with leading `/`, only-missing upload,
oversize file skipped, expired-JWT session restart, multipart shape + MIME,
publish with completion JWT, metadata-only, `404-page`,
`auto-trailing-slash`, `_headers` in config, workers.dev enabled); `sslverify`
on every call; token never in messages; 403 → clear, token-free message.
`svelte-check`: 0 errors / 0 warnings; production build OK; `php -l` clean.
**Still to do:** run §10 against a real Cloudflare account; regenerate
translations.
