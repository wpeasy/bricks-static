# Connector transport — implementation plan

> Status: **phases 1 + 2 implemented** (2026-09-17); phase 3 (`manifest` op, probe via `ping`, WP-CLI) still open. Roadmap item "Connection Agent".
> Implementation notes where it differs from the text below: the receiver is a lintable template (`src/Deploy/templates/connector.php.txt`) rather than a heredoc; `put` was folded into `chunk` (a final chunk with `target` moves the file into place), so the op set is `ping stat get chunk extract delete`; the UI is a Segmented FTP-vs-Connector choice (it IS the saved `transport`), with Manual ZIP as a separate always-on section; the download is an explicit single-use link rather than an auto-navigation.
> A user-installed PHP receiver on the destination that replaces FTP/SFTP as
> the deploy transport. Read `SECURITY_PATTERNS.md` §3 first — the connector
> is the persistent successor of the one-shot `UnzipScript` helper and becomes
> the highest-impact surface in the plugin.

## 1. Why

Today's transports are FTP/FTPS/SFTP (per-file or package+one-shot helper) and a
manual Export ZIP. Both need credentials the user often doesn't have handy, and
FTP is slow for per-file uploads. Most static-capable hosts *do* run PHP at the
destination web root (the package path already depends on it).

A **connector** is a single self-contained PHP file the user downloads from the
dashboard and drops into the destination web root once. From then on the source
site talks to it over HTTPS: no FTP account, chunked zip upload (faster than FTP),
and the destination can answer questions (server type, writability, current
manifest) that FTP can't.

What it does **not** solve: nginx config (still PHP-FPM — can't write `/etc/nginx`
or reload; see the Tier 2 SSH note in `ServerProbe`), and non-PHP targets
(Netlify, S3, Cloudflare Pages).

## 2. Naming

- Transport id: `connector` (4th `Schema` `transport` enum value; label
  "Connector (HTTPS)"). Not "cloner" — nothing is cloned; it receives.
- File: `bs-connector-<16 hex>.php` (random per pairing; the name is part of the
  secret surface, so it is never listed anywhere public).
- PHP: `Deploy\ConnectorScript` (generator), `Transport\ConnectorTransport`,
  `Deploy\ConnectorClient` (HTTP protocol client).

## 3. Pairing model

One connector file = one **(source site, destination)** pair.

| Stored on source (Destination fields)        | Embedded in the connector file            |
|----------------------------------------------|-------------------------------------------|
| `connectorId` (16 hex, = filename suffix)    | `$ID` (same)                              |
| `connectorSecret` (32 bytes, `secret => true`, `Crypto`-encrypted at rest) | `$SECRET_HASH = hash('sha256', secret)` — **never the raw secret** |
| `connectorVersion` (protocol version paired) | `$VERSION` (protocol version)             |
| `destinationUrl` (already exists — required) | `$SOURCE_HOST` (informational only, not trusted) |

Storing only the hash in the file means a PHP-handler misconfiguration that
serves the file as text leaks nothing reusable (the attacker would need the
preimage). The raw secret lives only in the source's encrypted option.

**Rotate / revoke**: dashboard button regenerates `connectorSecret` (and, on
revoke, clears `connectorId`). The old file then fails auth with a specific
error code (`paired_elsewhere`) and the dashboard says "download and replace the
connector". Rotation is the kill switch; there is no remote "self-delete" (a
remote delete verb would itself be an attack surface).

## 4. Wire protocol

All calls: `POST https://<destinationUrl>/bs-connector-<id>.php`, JSON body
(or multipart for chunk upload), JSON response. Every request carries:

```
X-BS-Id:        <connector id>
X-BS-Timestamp: <unix seconds>
X-BS-Nonce:     <16 hex>
X-BS-Signature: hex(hmac_sha256(secret, id . "\n" . timestamp . "\n" . nonce . "\n" . sha256(body)))
```

Connector verification order (fail closed, constant time, single generic 403
`{"ok":false,"error":"forbidden"}` for every auth failure so probes learn nothing):

1. Method is POST; `X-BS-Id` equals `$ID`.
2. `|now - timestamp| <= 300` (clock-skew window; blocks replay of old captures).
3. Nonce not seen in the last 300 s (`bs-connector-<id>.nonces` file next to
   the script, pruned each request; refuse if unwritable — no silent downgrade).
4. Recompute the HMAC. The connector cannot recompute it from `$SECRET_HASH`
   alone, so the request ALSO carries the raw secret in `X-BS-Key`; the
   connector checks `hash_equals($SECRET_HASH, hash('sha256', key))` **then**
   verifies the HMAC with `key`. Net effect: bearer (hash-stored) + signature
   (integrity over body) + timestamp/nonce (replay). TLS is still required —
   see §6 — the signature is defence in depth, not a substitute.
5. Failed-auth lockout: after 10 failures in 10 min (counter in the nonce file)
   respond 429 to everything for 10 min. The secret is 256-bit so brute force is
   moot; the lockout just makes scanning noisy and cheap to spot.

Operations (`{"op": …}`):

| op         | Body / form                                                     | Response                                                                                     |
|------------|-----------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| `ping`     | —                                                               | `{ok, version, php, zip:bool, gzip:bool, writable:bool, free:int, server:"<SERVER_SOFTWARE>", dir:"<basename>"}` |
| `manifest` | —                                                               | `{ok, files:{rel:{size,hash}}}` — walks its own dir, skips `.gz` siblings and itself; used to rebuild the pushed record after a wipe |
| `stat`     | `{paths:[…]}`                                                   | `{ok, exists:{rel:bool}}` (for `.htaccess` merge / backup check)                             |
| `get`      | `{path}`                                                        | `{ok, content:base64}` — **allow-listed to `.htaccess` and `.htaccess.bricks-static.bak` only** |
| `put`      | multipart `file` + `path`                                       | `{ok}` — single small file (`.htaccess`, per-file fallback)                                   |
| `chunk`    | multipart `file` + `upload`(id) + `index` + `total` + `sha256` (last chunk) | `{ok, received}` — appends to `bs-connector-<id>.<upload>.part`; on last chunk verifies sha256, renames to `.zip` |
| `extract`  | `{upload, deletes:[…]}`                                         | `{ok, extracted, deleted, errors:[…]}` — the `UnzipScript` body: zip-slip guard, gzip siblings, prune list; deletes the zip after |
| `delete`   | `{paths:[…]}`                                                   | `{ok, deleted}` (+ `.gz` siblings)                                                           |

Chunk size default 4 MB (below the common 8 MB `upload_max_filesize`; `ping`
returns `upload_max` so the client can size chunks). Extract is called with a
long timeout (`bs_package_timeout`, existing filter).

## 5. Filesystem jail (non-negotiable, mirrors `UnzipScript`)

- `$base = realpath(__DIR__)`. Every `rel` path goes through the existing
  `$safe()` normaliser (`\`→`/`, reject empty / absolute / `../` / `:`), then
  `realpath(dirname($base.'/'.$rel))` must start with `$base . '/'` (or equal
  `$base`) — catches symlink escapes the string check misses.
- **Write deny-list by extension** (case-insensitive, checked on the *final*
  extension and on any double extension like `x.php.txt` → reject if any
  segment is denied): `php php3 php4 php5 php7 php8 phtml phar phps pht cgi pl
  py sh htaccess-with-handlers` — concretely: refuse any `.htaccess` write that
  contains `AddHandler`, `AddType`, `SetHandler`, `php_value`, `php_flag`, or
  `Action` (a stolen secret must not be able to turn the jail into code exec).
  Static output never contains these, so nothing legitimate is blocked.
- Never touch its own file, the nonce file, or `*.part` files via `put`/`delete`.
- `delete` and `extract` deletes only remove **files**; directories are pruned
  only when empty and only under `$base`.

Threat outcome with a stolen secret: attacker can deface the static site
directory. They cannot execute code, read outside the directory, or read the
site's `.php` files (`get` is allow-listed). That is the same blast radius as a
leaked FTP account limited to the web root — acceptable, and better than FTP
(no code writes).

## 6. Transport security

- `destinationUrl` must be `https://` unless `UrlSafety::is_dev_host()`; the
  dashboard refuses to pair otherwise ("Connector needs HTTPS"). TLS verified
  via the existing `PackageDeployer::sslverify()` rule (§2 of SECURITY_PATTERNS).
- Every call goes through `UrlSafety::guarded_post()` (SSRF guard, redirects off).
  A destination that 301s `http→https` or adds a trailing slash fails fast with
  a fixable message rather than silently degrading.
- The connector sets `header('Cache-Control: no-store')`, `X-Robots-Tag: noindex`,
  and emits nothing on GET except a minimal 405 (no banner, no version).
- Response bodies are JSON and parsed with `json_decode(..., true)`; never
  `eval`ed or echoed to the browser unescaped. Error strings from the connector
  are `sanitize_text_field()`ed before they reach the job message.

## 7. Source-side integration

### 7.1 `Transport\ConnectorTransport implements TransportInterface`

Runner only uses `connect/disconnect/put/exists/get/delete` (verified by grep);
implement all eight for the interface:

| Interface       | Connector op                                              |
|-----------------|-----------------------------------------------------------|
| `connect()`     | `ping` — throws with the connector's error on failure (also caches `zip`, `gzip`, `upload_max`) |
| `disconnect()`  | no-op                                                     |
| `put()`         | `put` (single file); used for `.htaccess` and the per-file fallback |
| `exists()`      | `stat`                                                    |
| `get()`         | `get` (allow-listed; throws for anything else)            |
| `delete()`      | `delete`                                                  |
| `mkdir()`       | no-op (connector creates parents on write)                |
| `rename()`      | unsupported → `false` (nothing in Runner needs it)        |

Plus a **`PackageCapableTransport`** interface (new, `Transport/`):
`deploy_package(string $zip_path, array $deletes, ?callable $progress): array`
returning the same shape as `PackageDeployer::deploy()`. `PackageDeployer::deploy()`
gains one early branch: `if ($transport instanceof PackageCapableTransport)` →
build the zip as today, then `chunk`×N + `extract` instead of uploading a helper.
The FTP path is untouched. `PackageDeployer::can_build()` (source-side
ZipArchive) still gates packaging; without it the connector falls back to
per-file `put` (already supported by `tick_upload`), which is *still* faster than
FTP because there's no connection handshake per file.

### 7.2 `Deploy\ConnectorClient`

Stateless HTTP client used by the transport: signs requests (§4), uses
`guarded_post()`, maps HTTP/JSON errors to typed exceptions
(`ConnectorAuthException` → "re-download the connector", `ConnectorVersionException`
→ "connector is outdated", generic `RuntimeException` otherwise). Retries only
idempotent ops (`ping`, `stat`, `manifest`, `chunk` for the *same* index) via the
existing `put_retry()` pattern.

### 7.3 `Deploy\ConnectorScript::generate(string $id, string $secret_hash, int $version): string`

Same heredoc-generation style as `UnzipScript`. Constraints on the emitted file:
PHP 7.4+ syntax, zero dependencies, no `short_open_tag`, works with
`ZipArchive` absent (reports `zip:false`, refuses `chunk`/`extract`, per-file
`put` still works), and gzip sibling generation reuses the exact `UnzipScript`
logic (extract that into a shared `Deploy\HelperFragments` so the two scripts
can't drift). `UnzipScript` stays for FTP destinations.

### 7.4 Settings / Schema

New `Schema::fields()` entries: `connectorId` (string), `connectorSecret`
(`secret => true`, constant `BS_CONNECTOR_SECRET`), `connectorVersion` (int).
`transport => 'connector'` makes `host/port/username/password/remotePath`
irrelevant — `Destination::connection_config()` returns `transport`, `url`,
`connectorId`, `connectorSecret` (decrypted only here, same rule as passwords).
`for_display()` exposes `connectorId` and `hasValue` for the secret, never the
secret. `TransportFactory::capabilities()` adds `connector => true` (no runtime
dependency beyond `wp_remote_*`); `make()` routes `'connector'`.

`Destinations::update()` target-signature logic: changing `destinationUrl` or
rotating the secret counts as a re-target (drops pushed manifest, `bs_pkg_off_`,
`ServerProbe` cache) — the connector's `manifest` op can rebuild the pushed
record on the next Check instead of forcing a full re-upload (nice-to-have,
phase 3).

### 7.5 REST (all `manage_options`, `SyncController`/`ConnectionController`)

| Route                                  | Purpose |
|----------------------------------------|---------|
| `POST /connection/connector/pair`      | Generate id + secret for a destination (rotates if present), store, return `{id}`. |
| `GET  /connection/connector/download`  | Streams the generated PHP as `attachment; filename="bs-connector-<id>.php"`. **Single-use transient token** issued by `pair`, exactly like `/export/download` — keeps the "REST-only, no `admin_post_*`" invariant and means the file is never fetchable from a bare URL after the fact. |
| `POST /connection/connector/revoke`    | Clear id + secret; transport falls back to `ftps` default. |
| `POST /connection/test` (existing)     | For `transport=connector` runs `ping` and reports `{ok, php, zip, writable, free, server}`. |

### 7.6 Dashboard (`DestinationPanel.svelte`)

- Transport `Select` gains "Connector (HTTPS)". Selecting it swaps the
  host/port/user/password/remote-path fields for a **Connector** block:
  1. Destination URL (required, must be https).
  2. **Pair & download** `Button` → `pair` then download. Below it: "Upload
     `bs-connector-<id>.php` to the folder served at `<destinationUrl>`, then
     press Test."
  3. `Status`: unpaired / paired-untested / connected (with php version, zip,
     free space from `ping`) / auth failed ("re-download").
  4. `ConfirmButton`s: **Rotate** (new secret, re-download) and **Revoke**.
- `MethodPanel` transport label: "Connector (HTTPS)". `ServerProbe` can prefer
  `ping.server` over the `Server:` header when the destination is a connector
  (exact even behind a CDN).
- i18n: all strings via `I18n.php` dictionary (per project rule).

### 7.7 WP-CLI

`wp bs connector pair <dest>` / `download <dest> --to=<path>` / `revoke <dest>`
— thin wrappers over the same seams, for headless setups.

### 7.8 Abilities API

No new abilities in v1. If added later, `pair`/`revoke` are `sync`-tier
(they change what a deploy can reach); `ping` is read-only.

## 8. Phases

| Phase | Deliverable | Est. |
|-------|-------------|------|
| 1 | `ConnectorScript` + `ConnectorClient` + `ConnectorTransport` (per-file ops only), Schema fields, `pair/download/revoke` REST, Test wiring, DestinationPanel block. Deploys via existing `tick_upload` (per-file `put`). SECURITY_PATTERNS §3b written. | ~2 days |
| 2 | `PackageCapableTransport` + chunked upload + `extract`; `PackageDeployer` branch; shared `HelperFragments` for gzip/zip-slip. Progress reporting through `$progress`. | ~1 day |
| 3 | `manifest` op → "Rebuild push record from destination" action on Check; `ServerProbe` uses `ping.server`; WP-CLI commands; docs page + README + readme.txt. | ~1 day |

Ship phase 1+2 together (per-file-only would be slower than the FTP package path
and disappoint); phase 3 can follow.

## 9. Tests / verification checklist

- Unit-ish (`wp eval-file` scripts as done for `ServerProbe`): signature round-trip,
  timestamp window, nonce replay rejected, lockout, `$safe()` cases incl. symlink
  escape and `x.php.txt`, `.htaccess` handler-directive rejection, `get`
  allow-list, chunk reassembly + sha256 mismatch.
- Integration on Local: drop the connector into a second Local site's web root
  (nginx + PHP), pair, Test, full Sync (package), single-page sync, prune, Check
  → in sync, rotate → old file 403s, revoke.
- Negative: destination that 301s, `http://` public URL refused, host without
  ZipArchive falls back to per-file, connector served as plain text (no PHP)
  → clear "host doesn't run PHP here" error, not a JSON parse blowup.
- `npm run check`, `npm run build`, `php -l` on the *generated* connector for
  each PHP version in CI matrix (7.4, 8.0–8.4).

## 10. Open questions

1. **Multiple sources → one destination?** Model says no (one file per pair). If
   someone wants staging+prod sources pushing to one target, they pair twice and
   have two files. Fine for v1; document it.
2. **Directory jail vs. sub-path**: connector lives in the served root; `basePath`
   ("Served from sub-path") is a URL concern only and stays as-is. Confirm no
   deploy ever writes above the connector's directory (it doesn't today — all
   paths are manifest-relative).
3. **Should `extract` stream progress?** Not in v1 — one long request with the
   existing `bs_package_timeout`. If very large sites need it, a `status` op
   polling a progress file is the follow-up.
4. **Version drift**: the source pins `connectorVersion`; the file reports
   `$VERSION`. Minor protocol additions are backward-compatible; a breaking change
   bumps the major and the dashboard shows "connector outdated — re-download"
   (auth still succeeds so the message is precise).
