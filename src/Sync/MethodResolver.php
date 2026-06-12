<?php
/**
 * Resolves the method that a sync run will use (for display).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Read-only resolver describing how a sync will run: discovery mode, transport,
 * compression, server target, and link-rewriting style. Surfaced in the
 * dashboard's "Method" panel so the behaviour is never a black box.
 */
final class MethodResolver {

    /**
     * Transient caching the sitemap probe (avoids loopback on every poll).
     */
    private const SITEMAP_TRANSIENT = 'bs_sitemap_probe';

    /**
     * Candidate sitemap paths, in priority order.
     */
    private const SITEMAP_CANDIDATES = ['wp-sitemap.xml', 'sitemap.xml', 'sitemap_index.xml'];

    /**
     * Full resolved method description.
     *
     * @return array<string,mixed>
     */
    public static function resolve(): array {
        return [
            'discovery'    => self::discovery(),
            'transport'    => (string) Settings::get('transport'),
            'compression'  => ['gzip' => function_exists('gzencode')],
            'serverTarget' => ['htaccess' => true, 'nginxSnippet' => true],
            'links'        => 'root-relative',
        ];
    }

    /**
     * Discovery mode: sitemap if one is present, otherwise crawl from home.
     *
     * @return array<string,mixed>
     */
    private static function discovery(): array {
        $sitemap = self::detect_sitemap();

        return $sitemap !== null
            ? ['mode' => 'sitemap', 'sitemap' => $sitemap]
            : ['mode' => 'crawl', 'seed' => home_url('/')];
    }

    /**
     * Detect a published sitemap via a cached HEAD probe.
     *
     * @return string|null The sitemap URL, or null if none responds 200.
     */
    private static function detect_sitemap(): ?string {
        $cached = get_transient(self::SITEMAP_TRANSIENT);
        if (is_array($cached)) {
            return $cached['url'] ?? null;
        }

        $found = null;
        foreach (self::SITEMAP_CANDIDATES as $path) {
            $url      = home_url('/' . $path);
            $response = wp_remote_head($url, ['timeout' => 5, 'redirection' => 2]);

            if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
                $found = $url;
                break;
            }
        }

        set_transient(self::SITEMAP_TRANSIENT, ['url' => $found], 5 * MINUTE_IN_SECONDS);

        return $found;
    }
}
