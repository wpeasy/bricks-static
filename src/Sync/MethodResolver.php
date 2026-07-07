<?php
/**
 * Resolves the method that a sync run will use (for display).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\Discovery\UrlCollector;
use WPEasy\BricksStatic\Settings\Settings;

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

        return [
            'discovery'    => [
                'mode'        => $mode,
                'description' => $description,
            ],
            'transport'    => (string) Settings::get('transport'),
            'compression'  => ['gzip' => function_exists('gzencode')],
            'serverTarget' => ['htaccess' => true, 'nginxSnippet' => true],
            'links'        => 'root-relative',
        ];
    }
}
