# Changelog

All notable changes to **Bricks Static** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial project scaffold.
- Base framework CSS (`assets/css/bs-framework.css`): fluid spacing and type scales, border tokens, and a light/dark admin color palette scoped to `.bs`.
- Plugin bootstrap (`bricks-static.php`) with a self-contained PSR-4 autoloader, `Plugin` bootstrap class, and a placeholder top-level admin page that enqueues the base framework CSS.
- **M1 — Connection & settings:** encrypted (AES-256-GCM) FTP/SFTP credential storage with wp-config constant overrides; transport layer (SFTP via bundled phpseclib, FTPS/explicit-TLS and plain FTP via ext-ftp) with runtime capability detection; REST API (`bs/v1`) for connection settings, connection testing, and status; staging-cache path resolver (`wp-content/cache/bricks-static/` with uploads fallback).
- Svelte 5 + Vite admin dashboard shell: Status, Method (shows the resolved discovery/transport/compression/target), and Connection panels.
