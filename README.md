# Bricks Static

Generate and serve static HTML versions of Bricks-built pages for performance.

## Overview

**Bricks Static** renders pages built with the [Bricks](https://bricksbuilder.io/) builder to static HTML and serves them directly, reducing server load and improving front-end performance.

## Requirements

- **WordPress:** 6.5 or later (required for `wp_enqueue_script_module()` ESM support)
- **PHP:** 8.0 or later

## Project Properties

| Property | Value |
|----------|-------|
| PHP Namespace | `Bricks\StaticPlugin` |
| Constants Prefix | `BS_` |
| Textdomain | `bricks-static` |
| REST API Namespace | `bs/v1` |
| Database Table Prefix | `bs_` |

## Development

Project conventions and required reading live in [`CLAUDE.md`](CLAUDE.md):

- [`CODE_STANDARDS.md`](CODE_STANDARDS.md) — naming, security, PHP/JS/CSS standards
- [`WORDPRESS.md`](WORDPRESS.md) — plugin header template and WordPress configuration
- [`SVELTE5_IMPLEMENTATION.md`](SVELTE5_IMPLEMENTATION.md) — Svelte 5 runes and patterns
- [`assets/css/bs-framework.css`](assets/css/bs-framework.css) — base framework: design tokens and base styles

## License

GPL-2.0-or-later
