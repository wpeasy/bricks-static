# WORDPRESS Config

This file defines the standard WordPress plugin header structure. All project-specific values are sourced from **CLAUDE.md**.

## Plugin Header Template

```php
/**
 * Plugin Name:       {from CLAUDE.md: Plugin Name}
 * Description:       {from CLAUDE.md: Description}
 * Version:           0.0.1-beta
 * Requires at least: {from CLAUDE.md: Minimum WordPress}
 * Requires PHP:      {from CLAUDE.md: Minimum PHP}
 * Author:            BRXProd
 * Author URI:        https://brxprod.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       {from CLAUDE.md: Textdomain}
 * Domain Path:       /languages
 */
```

## Header Properties Reference

| Property | Source | Description |
|----------|--------|-------------|
| Plugin Name | CLAUDE.md: Plugin Name | Display name in admin |
| Description | CLAUDE.md: Description | Short description |
| Version | Start at `0.0.1-beta` | Current version (SemVer) |
| Requires at least | CLAUDE.md: Minimum WordPress | Minimum WordPress version |
| ~~Tested up to~~ | **Never in the PHP header** — `readme.txt` only | wordpress.org's upload scanner rejects it in the header (`plugin_header_tested_up_to_not_allowed`) |
| Requires PHP | CLAUDE.md: Minimum PHP | Minimum PHP version |
| Author | `BRXProd` (no email — support is via the wordpress.org forum) | Fixed |
| Author URI | `https://brxprod.com` | Fixed |
| License | `GPL-2.0-or-later` | Fixed |
| License URI | `https://www.gnu.org/licenses/gpl-2.0.html` | Fixed |
| Text Domain | CLAUDE.md: Textdomain | Translation text domain |
| Domain Path | `/languages` | Fixed |
| Plugin URI | — omitted | The wordpress.org listing is the plugin's home page |
| Network / Update URI | — omitted | wordpress.org rejects `Network: false`, and `Update URI` blocks wordpress.org updates |

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
