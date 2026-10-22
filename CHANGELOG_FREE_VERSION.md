# Changelog

## v2.1.2 (2026-10-23)

### New
Deleting the plugin now cleans up after itself. It removes its settings, your destinations (including any saved passwords and API tokens) and its cache folder. Files already published to your destinations are left exactly as they are.

### Fixed
WordPress can now offer updates for Bricks Static. A setting in the plugin file was stopping update checks.

### Improved
The documentation now explains the name. Bricks Static is Bricks-aware: Bricks gives page elements new random IDs every time a page is built, and the plugin ignores them, so only pages that really changed are uploaded. It works just as well with any standard WordPress content, including block editor and Classic Editor pages, archives and other themes.

### Improved
The French, German, Italian, Spanish and Dutch translations now cover the Cloudflare deploy method.

### Improved
Exported pages now identify themselves as Bricks Static in their generator tag.

### Improved
For developers: every setting, filter and constant now starts with `bricks_static_` / `BRICKS_STATIC_` instead of `bs_` / `BS_`, and the REST API lives at `bricks-static/v1`. If you set connection details in `wp-config.php`, rename them (for example `BS_HOST` becomes `BRICKS_STATIC_HOST`), and rename any custom filters you added.
