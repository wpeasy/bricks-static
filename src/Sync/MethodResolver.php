<?php
/**
 * Resolves the method that a sync run will use (for display).
 *
 * @package WPEasy\ShipStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Sync;

use WPEasy\ShipStatic\Discovery\UrlCollector;
use WPEasy\ShipStatic\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Read-only resolver describing how a sync will run: discovery, transport,
 * compression, server target, and link-rewriting style. Surfaced in the
 * dashboard's "Method" panel.
 *
 * Deliberately does NO network I/O — it is called when rendering the dashboard,
 * and a loopback request there can deadlock low-worker setups (e.g. Local).
 */
final class MethodResolver {

    /**
     * Full resolved method description.
     *
     * @return array<string,mixed>
     */
    public static function resolve(): array {
        $mode = UrlCollector::mode();

        $description = match ($mode) {
            'all'    => 'Every published page, post and taxonomy archive — including content nothing links to.',
            'manual' => 'Only the pages you mark “Include” (per-post, in the editor / Pages list / Sync panel).',
            default  => 'Internal-link crawl from the home page — only pages reachable by following links.',
        };

        $transport  = (string) Settings::get('transport');
        $cloudflare = $transport === 'cloudflare';
        $github     = $transport === 'github';

        return [
            'discovery'    => [
                'mode'        => $mode,
                'description' => $description,
            ],
            'transport'    => $transport,
            // Cloudflare and GitHub Pages compress themselves (no .gz siblings needed).
            'compression'  => ['gzip' => $cloudflare || $github || function_exists('gzencode')],
            // Cloudflare gets `_headers` instead of .htaccess / the nginx
            // snippet; GitHub Pages takes no server rules at all.
            'serverTarget' => [
                'htaccess'          => !$cloudflare && !$github,
                'nginxSnippet'      => !$cloudflare && !$github,
                'cloudflareHeaders' => $cloudflare,
            ],
            'links'        => 'root-relative',
        ];
    }
}
