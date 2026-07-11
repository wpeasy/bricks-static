# Changelog

## v1.0.6 (2026-07-11)

### New
When a sync ends in error, its status badge is now clickable and opens a window showing the real reason it failed — including which destination(s) failed and why, plus a list of any individual files that ran into trouble. Previously you only saw a generic "some destinations failed" message with no way to see more.

### New
The Images panel now shows a row of tags for every page that already has a saved image swap, along with how many swaps are on it, so you can see at a glance which pages need attention and jump straight to one instead of picking blind from the page list.

### Fixed
Destinations set to serve the site from a sub-folder (the "Served from sub-path" option) now actually work. Previously this setting was shown in the dashboard but had no effect, so a site meant to be served from something like `/mysite/` still had that path baked into every link and file, and the destination's root page would 404. Static exports now correctly rewrite the site's paths and links to match the folder you configured.

### Improved
The "Media" panel and its labels have been renamed to "Images" throughout the dashboard — clearer wording, since this feature only ever swaps images.

### Improved
A failed sync's summary message now names the specific destination(s) that failed instead of a generic "some destinations failed to deploy."

### Improved
A failed sync's status badge is now shown in red, distinct from the amber used for a plain cancellation — previously both looked the same, making it hard to tell a real failure from a sync you simply stopped yourself.
