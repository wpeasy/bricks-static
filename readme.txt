=== Bricks Static ===
Contributors: wpeasy
Tags: static site, bricks, performance, static html, deployment
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Render your Bricks-built pages to static HTML and deploy them over SFTP/FTP for speed, security and cheap hosting.

== Description ==

**Bricks Static** turns the pages you build with the Bricks Builder into plain static HTML and pushes them to any SFTP/FTP host. Serving flat files means no PHP, no database and no plugin stack on the public site — so it's dramatically faster, cheaper to host, and a much smaller attack surface.

It crawls your site, renders each page, collects the CSS/JS/images, rewrites URLs to root-relative, and deploys the result — either file-by-file or as a single zip that's extracted on the destination. Prefer to deploy manually? **Export ZIP** packages the same render into a downloadable zip instead. A floating "sync this page" button lets you push a single edited page without rebuilding the whole site.

= Features =

* Render Bricks pages to static HTML — **unlimited pages** per sync
* Deploy to **unlimited destinations** over SFTP, FTP or FTPS, with sync-all
* **Text**, **Media**, **Links** and **Videos** replacements per destination — no per-page limits
* **Gzip pre-compression** (`.gz`) for faster delivery
* **Remote pruning** — delete files that no longer exist locally
* **Sitemap.xml + robots.txt** generation
* Per-file **and** one-shot package (zip) deploy
* **Export ZIP** — download the current render as a zip instead of uploading it, for manual deploys (no FTP/SFTP needed)
* Automatic `.htaccess` generation **plus** a copy-paste nginx snippet
* Favicon and asset handling
* "Sync this page" floating button for single-page pushes
* WP-CLI command for reliable syncs on any host

== Installation ==

1. Upload the `bricks-static` folder to `/wp-content/plugins/`, or install it from **Plugins → Add New**.
2. Activate **Bricks Static** through the **Plugins** menu.
3. Open **Bricks Static** in the admin menu and enter your destination's SFTP/FTP details.
4. Click **Check** to render a preview, then **Sync** to deploy.

Bricks Builder must be active to build pages, but Bricks Static can run on any host that allows outbound SFTP/FTP.

== Frequently Asked Questions ==

= Does this replace my server with static files automatically? =

No. It generates a static copy and deploys it to a destination you choose (a separate host, subdomain or bucket-backed server). Your WordPress install stays where it is and is used only to build and push.

= How many pages can I export? =

There's no limit — Bricks Static renders and syncs every page in your export.

= Which connections are supported? =

SFTP, FTP and explicit-TLS FTPS. SFTP uses a bundled pure-PHP library, so no special server extensions are required.

== Screenshots ==

1. The Bricks Static dashboard — destinations, status and sync controls.
2. Per-destination replacements — Text, Media, Links and Videos.
3. The "sync this page" button on the front end.

== Changelog ==

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

= 2.0.1 =
Important fix if you use maintenance or coming-soon mode: renders captured the holding screen and published it over the whole site without failing.

= 2.0.0 =
Every feature is now free — unlimited pages/destinations, Links/Videos replacements, gzip, pruning and sitemaps, with no license required.
