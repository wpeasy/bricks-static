=== ShipStatic ===
Contributors: brxprod
Tags: static site, bricks, performance, static html, deployment
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.1.7
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish your WordPress site as static HTML over SFTP/FTP, HTTPS, Cloudflare or GitHub Pages. Bricks-aware; works with any WordPress content.

== Description ==

**ShipStatic** turns your WordPress pages into plain static HTML and publishes them to your own host (SFTP/FTP or HTTPS), to Cloudflare or to GitHub Pages. Serving flat files means no PHP, no database and no plugin stack on the public site — so it's dramatically faster, cheaper to host, and a much smaller attack surface.

It crawls your site, renders each page, collects the CSS/JS/images, rewrites URLs to root-relative, and deploys the result — either file-by-file or as a single zip that's extracted on the destination. Prefer to deploy manually? **Export ZIP** packages the same render into a downloadable zip instead. A floating "sync this page" button lets you push a single edited page without rebuilding the whole site.

= Built for Bricks =

ShipStatic understands Bricks. Bricks Builder gives elements a new random ID (`brxe-…`, form field IDs and several `data-*` attributes) every time a page renders. To a generic static exporter, every Bricks page then looks changed on every run, so the whole site is re-uploaded each time and "in sync" never means anything. ShipStatic recognises those IDs and ignores them when comparing renders, so only pages whose content actually changed are uploaded. It also renders through Bricks' maintenance / coming-soon mode (which would otherwise be captured and published in place of every page), and keeps its "Sync this page" button working alongside the Bricks editor.

= Works with all standard WordPress content =

Bricks isn't required. Rendering is builder-agnostic: ShipStatic captures whatever your site outputs, including block editor (Gutenberg) and Classic Editor posts and pages, archives and taxonomy pages, and other themes. On pages not built with Bricks, the Bricks-specific handling simply has nothing to do. Another builder that adds its own per-render noise can register patterns with the `shipstatic_stable_hash_patterns` filter.

= Features =

* Render Bricks pages to static HTML — **unlimited pages** per sync
* Deploy to **unlimited destinations** over SFTP, FTP or FTPS, with sync-all
* **Text**, **Media**, **Links** and **Videos** replacements per destination — no per-page limits
* **Gzip pre-compression** (`.gz`) for faster delivery
* **Remote pruning** — delete files that no longer exist locally
* **Sitemap.xml + robots.txt** generation
* Per-file **and** one-shot package (zip) deploy
* **Connector (HTTPS)** — no FTP account needed: upload one small PHP receiver to the destination once, then every sync goes up as a single signed, chunked package
* **Cloudflare** — publish to Cloudflare's global network (as a static Worker) with an account ID, an API token and a Worker name; no web host needed
* **GitHub Pages** — publish to a branch of your GitHub repository; each sync is one commit containing only the files that changed
* **Export ZIP** — download the current render as a zip instead of uploading it, for manual deploys (no FTP/SFTP needed)
* Automatic `.htaccess` generation **plus** a copy-paste nginx snippet
* Favicon and asset handling
* "Sync this page" floating button for single-page pushes
* WP-CLI command for reliable syncs on any host

= Credits =

The plugin icon includes the [HTML5 logo](https://www.w3.org/html/logo/) by the W3C, licensed under [CC BY 3.0](https://creativecommons.org/licenses/by/3.0/).

== Installation ==

1. Upload the `shipstatic` folder to `/wp-content/plugins/`, or install it from **Plugins → Add New**.
2. Activate **ShipStatic** through the **Plugins** menu.
3. Open **ShipStatic** in the admin menu and enter your destination's SFTP/FTP details.
4. Click **Check** to render a preview, then **Sync** to deploy.

Bricks Builder is optional: ShipStatic works with any theme or editor and is Bricks-aware when Bricks is in use. Your WordPress host needs outbound SFTP/FTP or HTTPS access to reach the destination.

== Frequently Asked Questions ==

= Does this replace my server with static files automatically? =

No. It generates a static copy and deploys it to a destination you choose (a separate host, subdomain or bucket-backed server). Your WordPress install stays where it is and is used only to build and push.

= Do I need Bricks Builder? =

No. Any standard WordPress content exports fine: block editor, Classic Editor, archives, any theme. The name reflects the extra Bricks handling (ignoring the random element IDs Bricks regenerates on every render, and rendering through Bricks' maintenance mode), not a requirement.

= Where do I get support? =

In the plugin's support forum on WordPress.org: https://wordpress.org/support/plugin/shipstatic/

= How many pages can I export? =

There's no limit — ShipStatic renders and syncs every page in your export.

= Which connections are supported? =

SFTP, FTP and explicit-TLS FTPS; the Connector (signed HTTPS to a small receiver file you upload once); Cloudflare; and GitHub Pages. SFTP uses a bundled pure-PHP library, so no special server extensions are required.

= Why does the plugin run shell commands? =

Only to start its own WP-CLI commands (`wp shipstatic …`) as background processes, so a long sync keeps running after the admin page request ends and several destinations can deploy in parallel. The commands are built from fixed internal paths and arguments, never from request input. When `exec()`/`proc_open()` are disabled on the host, the plugin falls back to running the sync in the browser.

= Where is the source code? =

The admin interface is written in Svelte and shipped as compiled, minified JavaScript in `assets/dist/`. The full, human-readable source is on GitHub: https://github.com/wpeasy/shipstatic (the `src-svelte/` folder). To build it yourself: `npm install`, then `npm run build`.

== External services ==

ShipStatic sends data only to destinations you configure yourself. Nothing is sent until you test a connection or run a sync, and there is no tracking or telemetry.

**Your own servers (SFTP, FTP/FTPS, Connector).** When you test a connection or sync, the plugin connects to the host you entered and uploads the generated static files (HTML, CSS, JavaScript, images and other media from your site), plus `.htaccess`, `sitemap.xml` and `robots.txt` where enabled. For the Connector, it sends signed HTTPS requests to the receiver file you uploaded to that server. "Check server" also requests your Destination URL to read its HTTP response headers. These servers are yours or your host's; their terms are your hosting provider's.

**Cloudflare API (`api.cloudflare.com`)** — only for destinations set to the Cloudflare deploy method. When you test the connection or sync, the plugin sends your Cloudflare account ID and API token (as the request's authorization), the Worker name, the list of exported files with their content hashes and sizes, the contents of any files Cloudflare doesn't already have, and the site's cache-header rules. Cloudflare then serves those files publicly at your Worker's address. Cloudflare [Terms of Service](https://www.cloudflare.com/terms/) and [Privacy Policy](https://www.cloudflare.com/privacypolicy/).

**GitHub API (`api.github.com`)** — only for destinations set to the GitHub Pages deploy method. When you test the connection or sync, the plugin sends your GitHub access token (as the request's authorization) and the repository owner, name and branch; it reads the branch's file list, and uploads the contents of changed exported files, then creates a commit on that branch. GitHub Pages then serves those files publicly. GitHub [Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service) and [Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement).

**YouTube thumbnails (`img.youtube.com`)** — the Videos replacements panel in the admin dashboard shows a preview image for each YouTube embed found on a page, loaded by your browser from YouTube, which receives your IP address and the video ID. Nothing is sent unless you open that panel on a page with YouTube embeds. YouTube [Terms of Service](https://www.youtube.com/t/terms) and Google [Privacy Policy](https://policies.google.com/privacy).

== Screenshots ==

1. The ShipStatic dashboard — destinations, status and sync controls.
2. Per-destination replacements — Text, Media, Links and Videos.
3. The "sync this page" button on the front end.

== Changelog ==

= 2.1.7 =
* Changed: it's now clearer which destination you're editing — the Replacements panel names it, the selected destination tab stands out, and the progress panel says "Last sync" once a run has finished.

= 2.1.6 =
* Added: each destination now gets links for its own address. Publish the same site to two GitHub project sites, or a project site and your own domain, and both work — previously only the first destination's sub-path was used. Press Process once after updating.
* Changed: link replacements are stored as plain paths (e.g. `/about/`) and follow each destination's sub-path.
* Fixed: removing a destination now removes everything the plugin kept for it.

= 2.1.5 =
* Changed: a new logo — an old sailing ship — in the WordPress admin menu, the dashboard header and the plugin icon.

= 2.1.4 =
* Changed: the sub-path links are built for is now taken from the Destination URL (e.g. `/my-site/` for `https://owner.github.io/my-site/`), so it no longer has to be entered twice. The "Served from sub-path" field is only used when a destination has no Destination URL.
* Fixed: with a sub-path, the Images and Videos replacement panels showed every image as "not in library" with a broken thumbnail.

= 2.1.3 =
* Changed: the plugin is renamed from Bricks Static to ShipStatic (slug and text domain `shipstatic`, WP-CLI `wp shipstatic`). For developers, the prefix is now `shipstatic_` / `SHIPSTATIC_` (was `bricks_static_` / `BRICKS_STATIC_`) and the REST namespace `shipstatic/v1` — rename any `wp-config.php` constants and custom filters.
* Added: GitHub Pages deploy method — publish the static site to a branch of your GitHub repository with an access token. Each sync is one commit with only the files that changed; removed pages are deleted; an unchanged site makes no commit. A large first sync pauses for GitHub's upload limit and carries on by itself. Also available from WP-CLI (`wp shipstatic github`).
* Added: Test warns when a destination serves the site from a different sub-path than links are built for (which would show pages without styles or images).
* Fixed: "Served from sub-path" now also updates links, images and CSS when WordPress is installed at the domain root — previously only the files moved, so the published site appeared without styling.
* Fixed: no PHP 8.5 deprecation notice when generating the favicon.

= 2.1.2 =
* Added: deleting the plugin now removes everything it stored — settings, destinations (including saved passwords and API tokens) and its cache folder. Files already deployed to your destinations are not touched.
* Fixed: WordPress can now offer updates for the plugin.
* Changed: the plugin is published by BRXProd; support is via the WordPress.org forum.
* Changed: for developers, every option, hook/filter and constant now uses the `bricks_static_` / `BRICKS_STATIC_` prefix (was `bs_` / `BS_`), and the REST namespace is `bricks-static/v1`. Rename any `wp-config.php` constants (e.g. `BS_HOST` → `BRICKS_STATIC_HOST`) and custom filters.
* Changed: the documentation explains the name — Bricks Static is Bricks-aware but works with all standard WordPress content.
* Changed: translations cover the Cloudflare deploy method again; exported pages' generator tag names Bricks Static.

= 2.1.1 =
* Added: Cloudflare deploy method — publish the static site to Cloudflare's global network (as a static Worker) with just an account ID, an API token and a Worker name; no web host, PHP or FTP needed. Each sync uploads only the files Cloudflare doesn't already have and switches the site over in one step. Also available from WP-CLI (`wp shipstatic cloudflare`).
* Security: the Connector file is stricter (protocol 2) — `.htaccess` files are checked in every folder against a list of safe rules, unsigned requests can no longer hold a lockout, and the secret is never sent if the Destination URL is changed to http://. Connector destinations must be re-paired once (Re-pair, then upload the new file).
* Security: bundled phpseclib updated to 3.0.57.

= 2.1.0 =
* Added: Connector (HTTPS) transport — pair a destination with a single PHP receiver you upload once, then sync over signed HTTPS with no FTP account. Re-pair to rotate, Revoke to disconnect, Rebuild push record after a reset. Also available from WP-CLI (`wp shipstatic connector`).
* Added: "Check server" — probes the destination and reports the web server, gzip, and whether the cache rules are actually in effect (the way to verify a manual nginx paste).
* Added: Manual deploy section on each destination (Export ZIP + how-to), no longer gated on a tested FTP connection.
* Changed: the nginx snippet is presented as optional tuning — stock nginx already serves the static site.
* Fixed: the .htaccess backup is no longer overwritten with our own generated file on later syncs; DirectoryIndex keeps index.php as a fallback for destination roots above a PHP app.
* Changed: all five bundled translations now cover every UI string.

= 2.0.1 =
* Fixed: renders no longer capture the maintenance / coming-soon screen — the whole site could publish as the holding page, silently, because "coming soon" returns HTTP 200.
* Fixed: "Plain" permalinks are now caught with a blocking warning instead of collapsing every page into index.html.
* Changed: skipped pages name the likely cause for 503, 403 and 401 (maintenance mode, security plugin/firewall, HTTP auth).

= 2.0.0 =
* Every feature is now free, with no license required: unlimited pages and destinations, Links and Videos replacements, gzip pre-compression, remote pruning, and sitemap/robots.txt generation are all built in.

= 1.0.0 =
* First public release.
* Render Bricks pages to static HTML and deploy over SFTP/FTP/FTPS (file-by-file or single-zip package).
* Text replacements, `.htaccess` + nginx config, favicon, single-page sync, and WP-CLI sync.

== Upgrade Notice ==

= 2.1.7 =
Clearer labelling of which destination you're editing.

= 2.1.6 =
Every destination now gets links for its own address. Press Process once after updating.

= 2.1.5 =
A new look: the ShipStatic sailing-ship logo in the admin menu and dashboard.

= 2.1.4 =
The sub-path is now taken from the Destination URL, and image replacements work again on sites served from a sub-path.

= 2.1.3 =
The plugin is now called ShipStatic; developers should rename `BRICKS_STATIC_*` constants and `bricks_static_*` filters to `SHIPSTATIC_*` / `shipstatic_*`. Adds GitHub Pages as a deploy method, and fixes "Served from sub-path" on sites where WordPress is installed at the domain root.

= 2.1.2 =
Developers: options, filters and constants now use the `bricks_static_` / `BRICKS_STATIC_` prefix. Rename any `BS_*` constants in wp-config.php and any `bs_*` filters you added.

= 2.1.1 =
Security update for the Connector: after updating, press Re-pair on each Connector destination and upload the new connector file — older files report "outdated" until replaced. Also adds Cloudflare as a deploy method.

= 2.0.1 =
Important fix if you use maintenance or coming-soon mode: renders captured the holding screen and published it over the whole site without failing.

= 2.0.0 =
Every feature is now free — unlimited pages/destinations, Links/Videos replacements, gzip, pruning and sitemaps, with no license required.
