# Security Patterns — Bricks Static

> **Read this before working on any REST controller, the deploy layer
> (`PackageDeployer` / `UnzipScript`), the transports (`FtpTransport` /
> `SftpTransport`), URL fetching (`UrlSafety` / `PageRenderer`), credential
> storage (`Crypto`), or anything that renders stored HTML on the client.**
>
> These are this plugin's real, non-negotiable security rules. Each maps to an
> actual call site in `src/`. When you add a feature, check it against every
> rule below and keep this file accurate (it's the reference a future audit
> grades the code against — a wrong example is worse than none).

Namespace `WPEasy\BricksStatic`, REST namespace `bs/v1`, option/constant prefix
`bs_`/`BS_`.

---

## 0. Authorization model: admin-only, uniform

Every REST route in `src/REST/*` registers `permission_callback => [self::class, 'can_manage']`, and **`can_manage()` is `current_user_can('manage_options')`**. There are **no** `wp_ajax_*`, `wp_ajax_nopriv_*`, `admin_post_*`, or `__return_true` endpoints, and no public liveness probe. The admin page (`Admin\Menu`) is gated on `manage_options` and re-checks it in `render_page()`.

**Rules for new surfaces:**
- A new REST route is `manage_options` by default. If you need anything lower-privileged, that is a design change — justify it, and then the IDOR/ownership rules below apply.
- This plugin operates on **site-level** state (options, the rendered site, destinations) — there is no per-post/per-user object model, so there is currently no IDOR surface. If you ever read/write a request-supplied `post_id`/`user_id`/attachment id, you MUST check `current_user_can('edit_post', $id)` (ownership) **inside the handler**, not just the route capability. A `(?P<id>\d+)` path token is resolved before `permission_callback`; body params are not — re-assert in the handler.
- REST relies on the `wp_rest` nonce (`Admin\Menu` localizes it as `bsData.nonce`, the client sends `X-WP-Nonce`). Don't add a parallel auth path that skips it.

---

## 1. SSRF guard for user-supplied URLs (`Support\UrlSafety`)

SSRF risk is asymmetric: a remote browser can't reach `127.0.0.1` or the cloud-metadata endpoint `169.254.169.254`, but the WordPress server can. The plugin fetches one **user-supplied** URL server-side: a destination's public URL, POSTed to trigger the deploy helper (`PackageDeployer::deploy()`).

**Rule:** any server-side fetch of a URL that originated from a request MUST go through `UrlSafety`:

```php
use WPEasy\BricksStatic\Support\UrlSafety;

// Validates scheme (http/https), resolves the host to IP(s), and rejects
// private/reserved/link-local ranges. Loopback (127/8, ::1) is intentionally
// allowed so *.local dev destinations keep working. Then forces redirection=0
// and rejects a 3xx (so a public URL can't 302 to an internal address).
$response = UrlSafety::guarded_post($url, ['timeout' => 120, 'body' => $body]);
if (is_wp_error($response)) { /* blocked, redirected, or transport error */ }
```

- `UrlSafety::is_safe_url($url)` is the validator; `guarded_post()` is the validator + fetch with `redirection => 0`. Use `guarded_post()` for any new POST to a user URL; add a `guarded_get()` the same way if a GET case appears.
- **Why redirects-off matters:** `is_safe_url()` resolves the host once, then `wp_remote_*` does its own DNS lookup at fetch time (DNS-rebinding TOCTOU) and follows up to 5 redirects by default. `redirection => 0` + 3xx-reject closes both.
- **Known limitation / TODO:** the resolve-then-fetch gap is not fully closed (no IP pinning). Pin the resolved IP via a one-shot `http_api_curl` / `CURLOPT_RESOLVE` filter if this fetch is ever exposed below `manage_options`. Today it's admin-only and the helper response body is never returned to the caller (blind), so the residual risk is low.
- **`PageRenderer` is exempt by design:** it fetches the *site's own* pages/assets (home URL + same-origin crawl from `AssetExtractor`, which only emits same-origin links), so it is not a user-supplied-URL surface and it legitimately needs `redirection => 5` to crawl. Do not route it through `guarded_post`. If you ever make the renderer follow author-supplied off-site URLs, that changes and the guard applies.

---

## 2. TLS verification (`sslverify`)

**Never disable `sslverify` for a public host.** It is relaxed in exactly one situation — a **local/dev host that cannot hold a public CA certificate** — and the host test is exact:

```php
// PackageDeployer::sslverify() and PageRenderer::should_verify_ssl()
$local = UrlSafety::is_dev_host($host);   // localhost, *.local, *.test, or a loopback IP
return apply_filters('bs_package_sslverify', !$local, $url);
```

- `UrlSafety::is_dev_host()` uses **exact** matching. Do NOT reintroduce `strpos($host, '127.0.0.1') === 0` — that prefix test also matched `127.0.0.1.attacker.com` (a real public host an attacker can register), silently disabling TLS for it. This was a real audit finding.
- `.local` / `.test` are reserved special-use TLDs — never publicly resolvable or certifiable — so relaxing TLS for them is safe. Anything else verifies.
- The `bs_*_sslverify` filters exist for genuinely broken dev certs; do not use them to paper over a real cert problem on a public destination.

---

## 3. The remote deploy helper (`Deploy\UnzipScript`) — keep it hardened

`PackageDeployer` uploads a generated one-shot PHP helper to the destination web root and triggers it over HTTPS to extract the package server-side. This is the highest-impact surface in the plugin. The generated helper MUST keep all of:

- **128-bit random token** (`bin2hex(random_bytes(16))`) compared with **`hash_equals()`** (constant-time). Never `==`.
- **Expiry** (`time() > $EXPIRES`) — refuses to run, and self-deletes, after the TTL.
- **Zip-slip guard** — reject entries that are empty, absolute (`/…`), contain `../`, or contain `:` (Windows drive), after normalising `\` → `/`. Applies to extracted paths AND the `deletes` list.
- **Self-delete** on success and on expiry; does NOT self-delete on a bad token (the plugin cleans up over FTP instead, so a probe can't make it erase itself).

Don't add an entry-name or `deletes` path to a filesystem operation without running it through the `$safe()` normaliser first. The package is built locally and the helper only extracts what we uploaded — never accept a caller-supplied archive.

---

## 4. Credential storage (`Support\Crypto`)

FTP/SFTP passwords are encrypted at rest with **AES-256-GCM** (random 12-byte IV, 16-byte tag verified on decrypt). The key derives from the WordPress salts (`AUTH_KEY` etc.), falling back to `wp_salt('auth')`.

- **Rule:** the key must never fall back to **public-knowledge** material (site URL, plugin slug, a hardcoded string). `wp_salt('auth')` is acceptable because it is secret and per-install. If you change `key()`, keep that property.
- Secrets are decrypted only for the transport layer (`Destination::connection_config()`), never returned to the browser — `for_display()` exposes `hasValue`/`fromConstant`, not the value. Keep it that way.
- `wp-config` constants (`Schema` `constant` fields) override stored values on the primary destination; constant-backed fields are never overwritten by REST input.

---

## 5. Transport integrity (MITM)

- **SFTP host key is verified (trust-on-first-use).** `SftpTransport::verify_host_key()` reads `getServerPublicHostKey()` **before** `login()` (so credentials are never sent to an unverified host), records it per `host:port` on first connect, and refuses on a later mismatch (`hash_equals`) — an active MITM presenting a different key is rejected instead of capturing the password. Filter `bs_sftp_verify_host_key` can disable it; don't. Escape hatch for a legitimate key change (server rebuild/migration): the stored key is cleared on connection-target change (`Destinations::update`) and by "Reset sync state" (`SftpTransport::forget_host_key()`). Keep the read-before-login ordering if you refactor `connect()`.
- **Plain FTP** sends credentials and content in cleartext — this is an explicit user transport choice (FTPS/SFTP are preferred in the UI). Don't silently "upgrade" or hide the choice.
- Remote paths passed to `put`/`delete` derive from the site's own URLs/manifest, not direct request input. If a future feature lets a user supply a remote path, normalise `..` before `absolute()` (the per-file transports don't, unlike the package helper which does).

---

## 6. Client-side: stored HTML rendering

The only DOM-HTML sink is `{@html row.replace}` in `dashboard/TextReplacements.svelte` (and the `el.innerHTML = value` round-trip in `lib/RichTextEditor.svelte`), used for "rich" text replacements.

- **Safety rests on the server:** `Settings\Destination::sanitize_replacements()` runs **`wp_kses_post()`** on every `rich` replacement on write (and `sanitize_text_field()` on plain). Keep that sanitisation **unconditional** — the client does not re-escape at render time.
- Authorship is `manage_options`-only, so this is not a low-privilege stored-XSS vector today; it would become one if replacement editing were ever opened to a lower capability. Re-evaluate this sink if that happens.
- No other `innerHTML`/`{@html}`/`document.write` exists. There are **no** `postMessage` listeners and **no** `BroadcastChannel` — if you add either, validate `event.origin` AND `event.source` for postMessage, and authenticate BroadcastChannel messages with a per-session nonce (a same-origin channel is not a security boundary). Never `postMessage(payload, '*')`.
- The `wp_rest` nonce lives only in `bsData.nonce` (sent as `X-WP-Nonce`); never render it to the DOM and never put secrets in localStorage.

---

## 7. SQL & output

- Every `$wpdb` query (driver-lock / heartbeat / cancel in `Sync\Runner`) uses `$wpdb->prepare()` with `%s`/`%d` placeholders against the core `{$wpdb->options}` table, or `$wpdb->delete()` with an array. **No string interpolation of values.** Keep it that way; use `%i` for identifiers if a custom table is ever added.
- Escape at output: `esc_html`/`esc_attr`/`esc_url`; `wp_kses_post` for stored HTML. The admin template is fully escaped — keep any new PHP-rendered output escaped at the point of echo.

---

## 8. Sensitive logging

If you add `error_log()` for diagnostics, gate any **user/site content** (URLs with userinfo, credentials, response bodies, file contents, exception messages quoting upstream content) behind `WP_DEBUG`; metadata (provider, lengths, resolved IP, user id) may log unconditionally. On many shared hosts `wp-content/debug.log` is web-reachable.

---

## Audit checklist for new code

- New REST route → `manage_options` unless justified; nonce intact; ownership check if it touches a request-supplied object id.
- New server-side fetch of a request URL → `UrlSafety::guarded_post()` / `is_safe_url()`, never a bare `wp_remote_*`.
- New outbound call → `sslverify` left at the WP default (verified); relax only via `UrlSafety::is_dev_host()`.
- Touching `UnzipScript` → token + expiry + zip-slip + self-delete all intact.
- New `{@html}` / `innerHTML` → server-sanitise the source with `wp_kses_post`, or escape client-side.
- New `$wpdb` query → `prepare()` with placeholders.
- New `error_log` of content → gate behind `WP_DEBUG`.
