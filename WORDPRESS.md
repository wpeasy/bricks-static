# WORDPRESS Config

This file defines the standard WordPress plugin header structure. All project-specific values are sourced from **CLAUDE.md**.

## Plugin Header Template

```php
/**
 * Plugin Name:       {from CLAUDE.md: Plugin Name}
 * Plugin URI:        https://alanblair.co/{from CLAUDE.md: Textdomain}
 * Description:       {from CLAUDE.md: Description}
 * Version:           0.0.1-beta
 * Requires at least: {from CLAUDE.md: Minimum WordPress}
 * Tested up to:      {IMPORTANT: Use WebSearch to find the current latest WordPress version - do NOT guess}
 * Requires PHP:      {from CLAUDE.md: Minimum PHP}
 * Author:            Alan Blair <alan@alanblair.co>
 * Author URI:        https://alanblair.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       {from CLAUDE.md: Textdomain}
 * Domain Path:       /languages
 * Network:           false
 * Update URI:        false
 */
```

## Header Properties Reference

| Property | Source | Description |
|----------|--------|-------------|
| Plugin Name | CLAUDE.md: Plugin Name | Display name in admin |
| Plugin URI | `https://alanblair.co/` + CLAUDE.md: Textdomain | Plugin homepage |
| Description | CLAUDE.md: Description | Short description |
| Version | Start at `0.0.1-beta` | Current version (SemVer) |
| Requires at least | CLAUDE.md: Minimum WordPress | Minimum WordPress version |
| Tested up to | **Use WebSearch to find current version** | Latest tested WordPress version |
| Requires PHP | CLAUDE.md: Minimum PHP | Minimum PHP version |
| Author | `Alan Blair <alan@alanblair.co>` | Fixed |
| Author URI | `https://alanblair.co` | Fixed |
| License | `GPL-2.0-or-later` | Fixed |
| License URI | `https://www.gnu.org/licenses/gpl-2.0.html` | Fixed |
| Text Domain | CLAUDE.md: Textdomain | Translation text domain |
| Domain Path | `/languages` | Fixed |
| Network | `false` | Multisite network-wide activation |
| Update URI | `false` | Custom update server |

## Slugs Reference

| Slug Type | Source |
|-----------|--------|
| Plugin Slug | CLAUDE.md: Textdomain |
| Text Domain | CLAUDE.md: Textdomain |
| REST Namespace | CLAUDE.md: REST API Namespace |
| Database Prefix | CLAUDE.md: Database Table Prefix |
| Constants Prefix | CLAUDE.md: Constants Prefix |
| PHP Namespace | CLAUDE.md: PHP Namespace |

## CLAUDE.md Required Fields

For this template to work, CLAUDE.md must define:

- **Plugin Name** - Display name
- **Description** - Short description
- **Textdomain** - Plugin slug (used for Text Domain, Plugin URI)
- **Minimum WordPress** - e.g., 6.5
- **Minimum PHP** - e.g., 8.0
- **PHP Namespace** - e.g., `AB\PluginName`
- **Constants Prefix** - e.g., `ABPN_`
- **REST API Namespace** - e.g., `abpn/v1`
- **Database Table Prefix** - e.g., `abpn_`
