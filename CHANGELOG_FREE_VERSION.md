# Changelog

## v1.0.2 (2026-07-07)

### New
Media replacements — swap an image out for a different one, right in the dashboard. Free includes one swap per page (Pro removes that limit). A single swap now updates every size of the image at once, including lazy-loaded versions, and can also catch CSS background images, not just `<img>` tags.

### New
Free now lets you deploy to 2 destinations, up from 1.

### New
Concurrent syncs — a new setting (1 to 10, default 2) lets a sync push to several destinations at the same time instead of one after another, so multi-destination syncs finish faster. On hosts that don't support it, syncs automatically fall back to the one-at-a-time approach.

### New
AI assistant support. If your WordPress supports the new Abilities API, an AI assistant can read your sync status and, only if you switch on the opt-in toggles, change which pages export and run a sync. Both toggles are off by default.

### New
Choose exactly which pages export. A new "Manual" mode adds an Include switch to each page/post (in the editor, the list table, and the front-end panel) so you export only what you pick.

### New
See what will (and won't) go live. Process renders your pages on demand, then View list shows an Included / Excluded breakdown — with the reason a page is left out (e.g. nothing links to it) and warnings for things that won't work statically (like forms that post back to your site).

### New
Knows when it's out of date. Editing a page, changing its status, or updating a menu now flips the "In sync" indicator and prompts you to re-process — and tells you when a published page isn't in the export yet.

### New
Cleaner output. Static pages drop redundant WordPress-only links (feeds, oEmbed, etc.) while keeping your SEO and social tags, and carry a single "Bricks Sync by BRXProd" generator tag.

### Fixed
Fixed a stuck "Rendering Pages…" card that couldn't be closed after refreshing mid-run.

### Fixed
Fixed a package sync that could report a failure even though there was nothing to upload or delete — it now correctly reports "already up to date".

### Improved
Settings have moved into a new Settings drawer (the gear icon), which also gains a light/dark/auto theme switcher, an accent colour picker, and a compact view option — alongside the existing page-selection mode, sync-button toggle, AI toggles, and the new Concurrent syncs setting.

### Improved
Check now instantly previews what a sync would change, without re-rendering.

### Improved
Slow hosts can raise the render/fetch/upload timeouts.

### Improved
Single-page sync only offers to publish pages that are actually published.
