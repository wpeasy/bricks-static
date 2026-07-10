=== Bricks Static ===
Contributors: wpeasy
Tags: static site, bricks, performance, static html, deployment
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Render your Bricks-built pages to static HTML and deploy them over SFTP/FTP for speed, security and cheap hosting.

== Description ==

**Bricks Static** turns the pages you build with the Bricks Builder into plain static HTML and pushes them to any SFTP/FTP host. Serving flat files means no PHP, no database and no plugin stack on the public site — so it's dramatically faster, cheaper to host, and a much smaller attack surface.

It crawls your site, renders each page, collects the CSS/JS/images, rewrites URLs to root-relative, and deploys the result — either file-by-file or as a single zip that's extracted on the destination. Prefer to deploy manually? **Export ZIP** packages the same render into a downloadable zip instead. A floating "sync this page" button lets you push a single edited page without rebuilding the whole site.

= Free features =

* Render Bricks pages to static HTML (up to **10 pages** per sync)
* Deploy to **2 destinations** over SFTP, FTP or FTPS
* **Text** replacements per destination
* **Media** replacements — swap one image per page (Pro lifts the per-page limit)
* Per-file **and** one-shot package (zip) deploy
* **Export ZIP** — download the current render as a zip instead of uploading it, for manual deploys (no FTP/SFTP needed)
* Automatic `.htaccess` generation **plus** a copy-paste nginx snippet
* Favicon and asset handling
* "Sync this page" floating button for single-page pushes
* WP-CLI command for reliable syncs on any host

= Bricks Static Pro =

The optional [Bricks Static Pro](https://brxprod.com/bricks-static/) add-on removes the limits and adds the advanced tooling:

* **Unlimited pages** per sync
* **Unlimited destinations** + sync-all
* **Unlimited media replacements** per page (Free allows one per page)
* **Advanced replacements** — Links and Videos swaps per destination
* **Gzip pre-compression** (`.gz`) for faster delivery
* **Remote pruning** — delete files that no longer exist locally
* **Sitemap.xml + robots.txt** generation
* License-key activation, automatic updates and support

= Free vs Pro =

| Feature | Free | Pro |
| --- | --- | --- |
| Pages per sync | 10 | Unlimited |
| Destinations | 2 | Unlimited |
| Text replacements | Yes | Yes |
| Media replacements (per page) | 1 per page | Unlimited |
| Link / Video replacements | — | Yes |
| Gzip pre-compression | — | Yes |
| Remote pruning | — | Yes |
| Sitemap + robots.txt | — | Yes |
| Export ZIP (downloadable, no FTP) | Plain files + .htaccess | + gzip, sitemap.xml, robots.txt |

== Installation ==

1. Upload the `bricks-static` folder to `/wp-content/plugins/`, or install it from **Plugins → Add New**.
2. Activate **Bricks Static** through the **Plugins** menu.
3. Open **Bricks Static** in the admin menu and enter your destination's SFTP/FTP details.
4. Click **Check** to render a preview, then **Sync** to deploy.

Bricks Builder must be active to build pages, but Bricks Static can run on any host that allows outbound SFTP/FTP.

== Frequently Asked Questions ==

= Does this replace my server with static files automatically? =

No. It generates a static copy and deploys it to a destination you choose (a separate host, subdomain or bucket-backed server). Your WordPress install stays where it is and is used only to build and push.

= How many pages can the free version export? =

Up to ten pages per sync. Bricks Static Pro removes the limit.

= Which connections are supported? =

SFTP, FTP and explicit-TLS FTPS. SFTP uses a bundled pure-PHP library, so no special server extensions are required.

= Do I need Bricks Static Pro? =

No — the free version is fully functional for small sites. Pro is for larger sites and teams that need multiple destinations, advanced content replacements, gzip, pruning and sitemaps.

== Screenshots ==

1. The Bricks Static dashboard — destinations, status and sync controls.
2. Per-destination replacements (Text and Media are free; Links and Videos are Pro).
3. The "sync this page" button on the front end.

== Changelog ==

= 1.0.0 =
* First public release.
* Render Bricks pages to static HTML and deploy over SFTP/FTP/FTPS (file-by-file or single-zip package).
* Text replacements, `.htaccess` + nginx config, favicon, single-page sync, and WP-CLI sync.
* Free renders up to 10 pages and one destination; advanced features are available in the Bricks Static Pro add-on.

== Upgrade Notice ==

= 1.0.0 =
First public release of Bricks Static.
