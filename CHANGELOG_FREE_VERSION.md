# Changelog

## v2.1.3 (2026-10-23)

### New
Publish to GitHub Pages. Choose GitHub Pages under Deploy via, enter your repository and an access token, and each sync becomes one commit with only the pages and files that changed. Removed pages are deleted, an unchanged site makes no commit, and every deploy is in your repository's history. A large first sync pauses for GitHub's upload limit and carries on by itself.

### New
Test now warns when a destination serves your site from a different sub-path than its links are built for, and tells you how to fix it — before you end up with pages that load without their styles or images.

### Improved
The plugin has a new name: ShipStatic (formerly Bricks Static). Everything works the same way. If you set connection details in wp-config.php or added custom filters, rename them from BRICKS_STATIC_ / bricks_static_ to SHIPSTATIC_ / shipstatic_.

### Fixed
"Served from sub-path" now works when WordPress is installed at the root of its domain. Previously the files moved into the sub-path but the links didn't, so the published site appeared without styling.

### Fixed
No PHP 8.5 deprecation notice when the plugin creates a favicon.
