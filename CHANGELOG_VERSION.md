# Changelog

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
