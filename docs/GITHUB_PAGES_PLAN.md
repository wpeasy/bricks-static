# GitHub Pages transport — implementation plan

> Status: **implemented** (2026-10-23, v2.1.3) and verified against a real
> repository (§13, §14). §13/§14 override earlier sections where they differ.
> A sixth deploy method that publishes the export to **GitHub Pages** by
> committing it to a branch through the GitHub REST API (Git Data endpoints),
> with no FTP account, no PHP host and no GitHub Actions workflow. It is the
> second **snapshot** transport after Cloudflare (`docs/CLOUDFLARE_PLAN.md`),
> and reuses that phase. Read `SECURITY_PATTERNS.md` (TLS/`sslverify`, secret
> storage, §3c Cloudflare) before touching the HTTP client.

## 1. Why

GitHub Pages is free static hosting with HTTPS, custom domains and a CDN, and
many developers already have a GitHub account. Publishing as git commits also
gives a readable deploy history and rollback with an ordinary `git revert`.

## 2. Branch commit vs Actions workflow — which mechanism

| | **A. Commit to a branch (chosen)** | B. Actions workflow (`deploy-pages`) |
|---|---|---|
| Pages source | "Deploy from a branch" | "GitHub Actions" |
| What we send | Only changed files (git blob SHA computed locally) | Whole site every time (zip as a release asset), then `workflow_dispatch` |
| Repo needs | Nothing extra | A workflow file we commit and keep current |
| Token permissions | Contents RW (+ Pages, optional) | Contents RW, Actions RW, Workflows RW |
| Removed pages | Absent from the new tree → gone | Absent from the artifact → gone |
| Cost | API rate limits on the first big deploy (§6) | Actions minutes (free on public repos), slower |

A wins on simplicity, permissions and change detection. B is the escape
hatch if very large sites hit the limits in §6 — out of scope for now.

## 3. Naming

- Transport id `github` (6th `Schema` `transport` enum value, after
  `cloudflare`); UI label **GitHub Pages**.
- Fields `ghOwner`, `ghRepo`, `ghBranch` (default `gh-pages`), `ghFolder`
  (`/` or `/docs` — the only two Pages allows), `ghToken` (`secret => true`).
- Constants `SHIPSTATIC_GH_OWNER` / `_GH_REPO` / `_GH_BRANCH` / `_GH_FOLDER` /
  `_GH_TOKEN`.
- Classes `Deploy\GitHubClient`, `Transport\GitHubTransport`.
- Filters `shipstatic_github_keep_paths`, `shipstatic_github_max_file_bytes`,
  `shipstatic_github_commit_message`.

## 4. GitHub API flow (one deploy)

All calls go to the fixed host `https://api.github.com` with
`Authorization: Bearer <token>`, `Accept: application/vnd.github+json`,
`X-GitHub-Api-Version` (the docs currently show `2026-03-10`; pin it after the
spike) and a `User-Agent: ShipStatic/<ver>` (GitHub rejects requests without one).

1. **Read the branch.** `GET /repos/{o}/{r}/git/ref/heads/{branch}` → head
   commit SHA (404 = branch doesn't exist yet → first deploy, orphan commit).
   `GET /repos/{o}/{r}/git/commits/{sha}` → tree SHA.
   `GET /repos/{o}/{r}/git/trees/{tree}?recursive=1` → every path + blob SHA.
   If `truncated` (over 100,000 entries / 7 MB), walk subtrees non-recursively.
2. **Diff locally.** For each file in the deploy set compute the git blob SHA,
   `sha1("blob {len}\0" . bytes)` (streamed — `hash_init('sha1')`). Equal to
   the remote entry → unchanged, send nothing. Remote paths inside our folder
   that aren't in the deploy set → delete, except the keep list (§5).
3. **Upload changed binaries.** `POST /git/blobs` `{content: base64, encoding:
   "base64"}` per file → SHA (should equal ours; mismatch = abort).
4. **Build the tree in batches.** `POST /git/trees` with `base_tree` = the
   current tree (or the previous batch's result), entries:
   - changed text files (valid UTF-8: HTML/CSS/JS/SVG/XML/JSON/TXT) inline
     as `{path, mode: "100644", type: "blob", content}` — no separate blob
     request;
   - changed binaries as `{…, sha}` from step 3;
   - deletions as `{…, sha: null}` (only for paths that exist — the API errors
     otherwise), in the last batch.
   Batch by payload size (~5 MB of JSON per request). First deploy: the first
   batch has no `base_tree`.
5. **Commit.** `POST /git/commits` `{message, tree, parents: [head]}` (no
   parents on the first deploy). Message:
   `ShipStatic 2.1.x: 12 changed, 3 removed` (filterable). Author = the token's
   user (omit `author`).
6. **Move the branch.** `PATCH /git/refs/heads/{branch}` `{sha, force: false}`
   — fast-forward only, so a commit pushed by someone else in the meantime
   isn't discarded: on a 422 re-read the branch and redo steps 1–6 once, then
   fail with a clear message. First deploy: `POST /git/refs`.
7. **Pages builds the site** (~3 s build + deploy for the spike site). We
   don't block on it; Test and Check show the build for **our** commit
   (`GET /pages/builds` matched by commit SHA) when the token can read it.
   A build for an older commit showing `errored` is normal — it was
   cancelled by the newer push (§13) — so never report those.

A single-page sync is the same flow: the diff finds one changed page, so it
costs ~5 requests.

## 5. What we own on the branch

- We own the **folder** (`/` = the whole branch, `/docs` = only `docs/`).
  Files outside it are never touched, so `/docs` on `main` can sit beside the
  repo's source code.
- Inside it, the branch mirrors the export, **except** the keep list:
  `CNAME` always (GitHub writes it when a custom domain is set in repo
  settings — deleting it would take the custom domain down), plus whatever
  `shipstatic_github_keep_paths` adds (e.g. `README.md`).
- We always add `.nojekyll` (empty). Without it Pages runs Jekyll over the
  files, which is slower and silently drops anything starting with `_`.
- When the Destination URL is a custom domain (not `*.github.io`) and no
  `CNAME` exists, we write one containing that host.
- The panel warns in plain words: "Everything in this folder is replaced on
  each sync."

## 6. Limits and how we handle them

From GitHub's docs (2026-10-23):

| Limit | Handling |
|---|---|
| **Secondary: 80 content-creating requests/min, 500/hour** (blob, tree, commit, ref writes all count) | The binding limit. Text files cost nothing extra (inline in tree batches). Binaries cost one request each: pace to ≤ 60/min, and stop at a budget of ~450/hour. A first sync with more binaries than that **pauses and resumes** (§7). Honour `retry-after` / `x-ratelimit-reset` on 403/429. |
| Primary: 5,000 requests/hour per token | Far above what one deploy needs. |
| 100 concurrent requests | We're sequential. |
| Files > 100 MB rejected by GitHub | Skip and report, like Cloudflare. Default cap **25 MiB** (`shipstatic_github_max_file_bytes`, max 100 MiB) — a blob travels base64 in JSON, so a 100 MB file needs ~300 MB of PHP memory. |
| Published site 1 GB; repo 1 GB recommended | Report the export size in Test/Check when it nears 1 GB. History grows only by changed files. |
| Pages builds: 10/hour soft limit | Each sync = one build. Note it in the how-to; repeated single-page syncs are the realistic way to hit it. |
| Deployment fails after 10 minutes | Not ours to control; a static site this size builds in well under that. |
| No custom headers / redirects on Pages | No `_headers`/`.htaccess` equivalent: our cache policy can't apply (Pages serves `max-age=600`). Pages compresses itself, so no `.gz` siblings. `404.html` is used automatically. |
| Private repos need a paid plan for Pages | State in the how-to. |

## 7. Resumable snapshot (new, Cloudflare doesn't need it)

`tick_snapshot()` runs the whole publish in one tick. That can't work when a
first deploy has to wait for the hourly budget. Add:

- **`Transport\ResumableSnapshotTransport extends SnapshotCapableTransport`**
  with `deploy_snapshot_step(array $files, array &$state, ?callable $progress): array`
  returning the usual result plus `pending: bool` and `waitUntil: int`.
- `tick_snapshot()` checks for it: on `pending` it saves `$state` into
  `job->data['snapshotState']`, sets the message (e.g. "Waiting for GitHub's
  upload limit — continues at 14:05 (312 of 1,480 images sent)"), calls
  `beat()` and returns; the phase stays `snapshot`. Ticks during a wait just
  beat and return, so the 90 s stale reaper never fires.
- **State** = remote head SHA, the tree SHA built so far, uploaded blob SHAs,
  the remaining queue. Each step works within a time budget (~20 s) so a
  browser-driven tick doesn't time out.
- **Keeping work safe across a long wait:** a tree that no ref points to may
  be garbage-collected. Before a pause longer than a minute, commit the
  partial tree to a hidden ref `refs/shipstatic/staging` (not a branch, so
  Pages ignores it); delete that ref after the final commit. (Spike: confirm
  the API accepts a ref outside `refs/heads`/`refs/tags` — the docs only
  require `refs/` plus two slashes.)
- Cancel: drop the state and the staging ref; nothing reached the Pages
  branch.
- Long waits need a driver that keeps ticking: WP-CLI spawn (background) does;
  the browser driver only while the dashboard is open. The message says so.

Also generalise the Cloudflare-specific text in `tick_snapshot()` /
`finalize` ("Publishing to Cloudflare failed", "Cloudflare connection failed",
the skipped-files note) to use the transport's label.

## 8. Source-side integration

### 8.1 Settings / Schema
- `transport` enum gains `github`; `TransportFactory::make` / `capabilities`
  (needs only `wp_remote_request` + `hash` — always available),
  `needs_host('github') = false`, `is_snapshot('github') = true`.
- Strict validators (these go into API paths; also `rawurlencode` each
  segment):
  - owner `^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$`
  - repo `^[A-Za-z0-9._-]{1,100}$`, not `.`/`..`
  - branch `^[A-Za-z0-9._/-]{1,100}$`, no `..`, `//`, leading/trailing `/`
    or `.lock` suffix
  - folder: exactly `/` or `/docs`.
- `ghToken` encrypted with `Crypto` like `cfApiToken`; `for_display()` shows
  `{hasValue, fromConstant}` only.
- Destination identity signature (`Destinations.php`) = owner/repo/branch/folder.

### 8.2 `Deploy\GitHubClient`
- Fixed host, `sslverify => true`, **`redirection => 0`**: a renamed repo
  answers 301 — report "this repository moved to X, update the settings"
  rather than following (keeps the token on `api.github.com`).
- Methods: `get_ref`, `get_commit`, `get_tree` (with truncation walk),
  `create_blob`, `create_tree`, `create_commit`, `create_ref`, `update_ref`,
  `delete_ref`, `pages_site`, `create_pages_site`, `latest_build`, `whoami`
  (Test).
- Retry with backoff on 5xx; on 403/429 with `retry-after` or a rate-limit
  message, surface `waitUntil` to the transport instead of sleeping long.
- Surface GitHub's `message`; never log or return the token.

### 8.3 Test connection & server check
- `POST /destinations/{id}/test` → `GET /repos/{o}/{r}` (token valid, repo
  exists — but NOT `permissions.push`: that is the *user's* role, not the
  token's, §13), then a write probe `POST /git/blobs` with empty content
  (one harmless, unreferenced write; 403 = token lacks Contents write), then
  `GET /repos/{o}/{r}/pages` (needs Pages read; skip quietly on 403):
  - Pages on → return `html_url` as `suggestedUrl` (fills an empty
    Destination URL) and warn if its source branch/folder differ from ours;
  - Pages off → with branch `gh-pages` say "GitHub turns Pages on
    automatically on the first sync" (§13). Any other branch/folder: link to
    the repo's Settings → Pages with the exact choice to make. No API
    "turn on" button — it needs **Administration: write** on the token,
    which is far broader than we want to ask for.
- `ServerProbe`: GitHub Pages answers `server: GitHub.com` — report it,
  skip the `.htaccess`/nginx advice.

### 8.4 The sub-path problem (all destinations, surfaced by this one)
A project site is served at `https://<owner>.github.io/<repo>/`, but links are
rendered for one `basePath`, read from the **primary** destination
(`Support\Url::base_path()`). So:
- GitHub Pages works as-is for user/org sites (`<owner>.github.io`), custom
  domains, or when it is the primary destination with "Served from sub-path"
  set to `/<repo>`.
- Add a generic check (Test + Check, every transport): if the Destination
  URL's path differs from the rendered base path, warn "Links are built for
  `/` but this destination serves from `/<repo>/` — pages will have broken
  links." Test pre-fills the sub-path suggestion for a primary destination.
- Per-destination base paths (render once per base path) are a separate,
  larger change — out of scope.

### 8.5 Dashboard (`DestinationPanel.svelte`)
- `Segmented` gains **GitHub Pages**.
- Fields: owner, repository, branch, folder (`/` | `/docs` `Select`), token
  (saved/clear behaviour as for Cloudflare).
- Short how-to: create a fine-grained token for this one repository with
  **Contents: Read and write** (and **Pages** to let the plugin turn Pages
  on); private repos need a paid plan; custom domains are set in the repo's
  Pages settings; the 10 builds/hour note.
- `Transport` union + capabilities in `shared/types.ts`; strings in
  `Support\I18n`; Docs page section.

### 8.6 WP-CLI
`wp shipstatic github set|test|status` — `set` takes `--owner`, `--repo`,
`--branch`, `--folder`, `--dest-url`, `--token-from-env` (reads
`GITHUB_TOKEN`, keeping the token out of shell history).

### 8.7 Abilities API
No new abilities — sync/status already go through `Runner`.

## 9. Security (add `SECURITY_PATTERNS.md` §3d)
- Token: encrypted at rest, admin-only REST, never echoed, only sent to
  `api.github.com`, no redirects followed.
- All path segments validated + encoded; the folder is one of two literals,
  so a deploy can never write outside it.
- Fast-forward-only ref update: the plugin can't silently discard someone
  else's commits on the branch.
- Least privilege: the how-to asks for a fine-grained token scoped to one
  repo (a fine-grained token's other grants can't be inspected, so Test only
  checks that push access is present).

## 10. Phases

1. **Spike** (WP-CLI, throwaway): publish a small export to a real test repo;
   confirm the API version header, the hidden staging ref, inline `content`
   for HTML with non-ASCII, tree batch size limits, Pages permissions.
   Needs a throwaway repo + fine-grained token from the user.
2. **Transport + Runner**: Schema fields/validators, `GitHubClient`,
   `GitHubTransport`, `ResumableSnapshotTransport` + `tick_snapshot` changes,
   label generalisation. Mocked-API test like the Cloudflare smoke test.
3. **Polish**: Test + Turn on Pages, sub-path warning, `ServerProbe`, panel
   + how-to, WP-CLI, i18n, Docs page, SECURITY_PATTERNS.md, CHANGELOG.

## 11. Verification checklist
- [ ] First deploy to a new `gh-pages` branch (orphan commit), Pages on, site loads.
- [ ] Second sync with no changes: no commit made, reported as in sync.
- [ ] Edit one page → one commit touching one file; single-page sync the same.
- [ ] Remove a page → deleted in the commit; `CNAME` and keep-list files survive.
- [ ] `/docs` on `main`: source files outside `docs/` untouched.
- [ ] Someone pushes to the branch mid-sync → retried once, never force-pushed.
- [ ] More binaries than the hourly budget → pauses, resumes, finishes; cancel cleans the staging ref.
- [ ] File over the cap → skipped and named in the result.
- [ ] Bad token / no push access / renamed repo → clear Test messages.
- [ ] Project site as non-primary destination → sub-path warning.
- [ ] Plugin Check 0 errors; `npm run check` clean; translations updated.

## 12. Open questions
- Make the `/docs`-on-`main` option visible from the start, or ship `/` only
  first? (Plan assumes both — it is a few lines once the folder scoping exists.)
- Should Test offer to create the repository too? (Needs a broader token —
  plan says no.)

## 13. Spike findings (2026-10-23, `wpeasy/shipstatic-pages-test`)

Throwaway script (`wp eval-file`, WordPress HTTP API, `sslverify` on,
`redirection => 0`) against a real public repo, fine-grained token with
Contents RW + Pages RW, publishing the playground render (38 files: 13 HTML,
11 JPG, CSS/JS/SVG, 3 MB) plus `.nojekyll`.

| Test | Result |
|---|---|
| API version `2026-03-10` | Accepted (`x-github-api-version-selected: 2026-03-10`). Pin it. |
| First deploy, orphan `gh-pages` | 18.8 s, 18 requests / **16 writes** (12 binary blobs + 2 tree batches + commit + ref). |
| Inline `content` for text files | Works; the SHA GitHub computes equals our local git blob SHA for all 27 text files, including non-ASCII (`ü`) — byte-exact, no line-ending change. Use the SHA comparison as the post-write check. |
| Binary blob SHA | Equals our local SHA. |
| Chained tree batches (`base_tree` = previous batch) | Works. |
| Sync with no changes | **No commit**: 3 reads (ref, commit, recursive tree), 0 writes. |
| One page edited + one removed | 5.6 s, **3 writes** (one tree with the inline edit + `sha: null` delete, commit, ref). Removed page 404s on the site. |
| Hidden ref `refs/shipstatic/staging` | Create 201 / read 200 / delete 204 — usable for §7. |
| Pages turned on | **Automatically**, by GitHub, on the first push of a branch named `gh-pages` (`build_type: legacy`, source `gh-pages` `/`). One data point — re-check with `/docs`/other branches, which likely need the repo setting. |
| `POST /repos/{o}/{r}/pages` | 403 — needs `pages=write,administration=write` (from `x-accepted-github-permissions`). Dropped from the plan (§8.3). |
| `GET /repos/{o}/{r}` `permissions` | Reports the *account's* role (admin/push all true), not the token's grants — useless for checking the token. Use the write probe. |
| Pages build status | Older commits pushed seconds apart show `errored: Page build failed`; the matching Actions runs are `cancelled` (superseded). Latest commit: `built` in ~3 s. |
| Served site | 200 for pages; `/page` → 301 `/page/`; `.nojekyll` served (0 B); `cache-control: max-age=600`; gzip on; `server: GitHub.com`; missing page → GitHub's own 404 when the export has no `404.html`. |
| Sub-path | Confirmed: links are root-relative (`href="/another-test/"`), so on a project site (`/shipstatic-pages-test/`) they point outside it. §8.4 is required, not optional. |
| Bug to avoid | A placeholder entry with a `null` source (`.nojekyll`) read as "absent" with `isset()` → the file was deleted. Use `array_key_exists()` for the keep/delete decision. |

**Project site, end to end (second round).** With the primary destination's
"Served from sub-path" set to `/shipstatic-pages-test` and re-rendered, all 8
pages and all 17 internal asset references return 200 and the site renders
fully (styles, fonts, slider JS). Two findings:

- **Bug (fixed in `Render\UrlRewriter`, all transports):** with WordPress at
  the domain root, a non-root sub-path moved the *files* under it but never
  prefixed the *links* — `apply_base_path()` returned early when `home_base()`
  is empty. `rewrite()` now puts the base where the source origin was
  (`https://host/x` → `/sub/x`, JSON-escaped forms included). `to_absolute()`
  must NOT do the same: sitemap entries are built from already-remapped paths,
  so prefixing there doubled the sub-path (caught in §14). Default base `/` →
  empty prefix → unchanged output.
- **File layout:** the export puts pages under `<sub>/…`, which is right for a
  web root that is the domain. A project site's repo root *is* `/<repo>/`, so
  `GitHubTransport` publishes `<sub>/…` at the repo root (strip the prefix).
  Files the export puts outside it (`favicon.ico`, `robots.txt`,
  `sitemap.xml` — domain-root files) go at the repo root too; on a project
  site they're served under `/<repo>/`, which is harmless but means
  `robots.txt` has no effect there — say so in the how-to.

Not covered yet: rate-limit pausing (§7 — needs > 450 binaries or a
simulated 403), `/docs` on `main`, custom domain/`CNAME`, truncated trees.
These go into the mocked-API tests of phase 2.

## 14. Implementation notes (as built, 2026-10-23)

- **No staging ref.** §7's `refs/shipstatic/staging` checkpoint was dropped: a
  pause only happens when the write budget is spent, so the checkpoint's own
  writes would hit the same limit, and GitHub keeps unreferenced objects far
  longer than any pause. Instead a create-tree 422 after a pause clears the
  uploaded set and re-uploads once.
- **Test permissions.** Pages: Read-only is enough (Test shows the status);
  Pages write isn't used. Recommended token: Contents RW (+ Pages R).
- **Verified end to end** on `wpeasy/shipstatic-pages-test` through
  `wp shipstatic sync --dest=…`: first sync 2 changed (robots/sitemap now use
  the Destination URL), re-sync "already up to date — no commit". Injected
  failures (`pre_http_request`): a secondary rate limit on the 3rd blob
  (paused, resumed), a 422 on the first create-tree (re-uploaded once), a 422
  on the first branch move (re-planned on the new head, no blobs resent) — all
  recovered, state surviving a JSON round-trip between steps. A following
  normal sync deleted the 6 injected files in one commit.
- **Sitemap double prefix** found and fixed in the first real sync (see §13).
- Secondary destinations still use the primary's sub-path (§8.4); Test warns.
