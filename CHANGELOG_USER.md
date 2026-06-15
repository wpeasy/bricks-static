# Changelog

A plain-English history of what changed in Bricks Static, written for the people who use it. For full technical detail, see `CHANGELOG.md`.

## v0.0.3-beta (2026-06-14)

### New
A new **Videos** section in Replacements. Swap a locally-hosted video using the WordPress media library, or replace a YouTube/Vimeo embed by pasting a URL or just the video ID (the same way Bricks accepts them — a full embed code works too). YouTube embeds show a thumbnail, and an embed's `origin` is automatically pointed at each destination's own domain so videos keep working after you deploy.

### New
A new **Data attributes** section. Replace the value of any `data-*` attribute (for example `data-test`) for a given destination. Only the value changes — never the attribute name — and only inside real tags, so your page text and scripts are never touched. Bricks' own internal attributes and random ids are hidden so your custom ones are easy to find.

### Fixed
"Only linked pages" again exports only the pages reachable from your home page. It had begun pulling in unlinked pages (such as the default Sample Page and Hello World post) and their assets. The generated `sitemap.xml` now lists exactly the pages that were actually exported.

### Fixed
Background images added through CSS — and any link or embed address containing `&` — are now read correctly. Previously these could be mangled into broken URLs, so the image wasn't uploaded and showed a 404 on the static site.

### Improved
Behind-the-scenes security hardening (safer outbound requests and verified SFTP connections). No change to how you use the plugin.

### Removed
The per-destination "Include in single-page sync" option has been removed — single-page sync now goes to every enabled destination.

## v0.0.2-beta (2026-06-13)

### New
**Media replacer** — swap any image or video on a page for another item from your media library, per destination. A swapped image keeps its responsive sizes so it looks right at every screen width, and replacements whose original is no longer on the site are cleaned up automatically.

### New
**Link replacer** — replace link targets (`<a>` and button links) per destination. Only real links are changed; matching words in your body text are left alone.

### New
**Single-page sync with a floating button.** A draggable "Sync this page" button on the front end and inside the Bricks editor (where it saves your editor changes first). It publishes just the page you changed — plus any brand-new pages it links to, so you never ship a dead internal link.

### New
**Sitemaps, robots.txt and a favicon** are generated and uploaded with your site, with their URLs pointed at each destination's domain. A `/favicon.ico` is included from your real favicon, your WordPress Site Icon, or a generated default.

### New
**Faster deploys.** Changed files are bundled and unpacked on the destination in a single step instead of one slow FTP transfer per file, with an automatic fall-back when a host can't run it.

### New
A **Documentation** page in the admin menu covering features, how it works, common issues, and troubleshooting.

### Fixed
A destination that has replacements no longer shows "Out of date" immediately after a successful sync.

### Fixed
Pre-compressed (gzip) sitemaps, robots.txt, SVG and JSON files are now served correctly on Apache — previously they could download as unreadable files. Very small files like robots.txt are no longer gzipped.

### Fixed
Fixed a fatal error that could occur during command-line and front-end syncs.

### Fixed
Dismissing the Progress card now stays dismissed after you reload the page.

### Improved
Text replacements can now be edited in a pop-up with a small rich-text editor — headings, lists, bold/italic/links, and an HTML source view.

### Improved
Text, Media and Link replacements are grouped into one accordion (a single section open at a time) to save space.

### Improved
A destination's Check and Sync buttons are disabled while that destination is turned off.

### Improved
Behind-the-scenes security hardening. No change to how you use the plugin.

## v0.0.1-beta

### New
The first release: generate static HTML copies of your Bricks-built site and push them to a destination over SFTP, FTPS or plain FTP.

### New
Sync one site to **multiple destinations**, each with its own connection and its own per-destination text replacements. The site is rendered once and sent to each destination in turn, with an "All Destinations" view to sync them together or one at a time.

### New
**Per-destination text replacements** — literal find-and-replace applied to your pages for a given destination.

### New
**Check** (a dry run that renders and catalogues everything without uploading) and **Sync** (render and upload), both with a live progress panel.

### New
Optional **pruning** to remove files from a destination that no longer exist locally, and **Reset sync state** to force a full re-upload.

### New
A **WP-CLI command** (`wp bricks-static sync`) to run a sync from the command line, plus on-screen guidance for local/dev setups where the command line is the more reliable option.

### New
**Server configuration** help: a managed `.htaccess` is uploaded automatically (gzip serving + caching) and an equivalent nginx snippet is provided to paste in.

### Fixed
Cancelling a run now reliably stops it.

### Fixed
Opening the dashboard no longer risks locking up servers that have very few PHP workers (such as Local).
