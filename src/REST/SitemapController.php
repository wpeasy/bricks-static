<?php
/**
 * Sitemap test endpoint: generate the dummy set, then parse it back.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\REST;

use WP_REST_Response;
use WPEasy\BricksStatic\Sitemap\SitemapGenerator;
use WPEasy\BricksStatic\Sitemap\SitemapParser;

defined('ABSPATH') || exit;

/**
 * Exposes the generate → parse round-trip so the dummy sitemaps and the parser
 * can be verified end to end before they are wired into the sync.
 */
final class SitemapController {

    /**
     * REST namespace.
     */
    private const NS = 'bs/v1';

    /**
     * Register routes.
     */
    public static function register(): void {
        register_rest_route(self::NS, '/sitemap/test', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'test'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);
    }

    /**
     * Capability gate.
     */
    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * GET /sitemap/test — generate the dummy set and parse it back to page URLs.
     */
    public static function test(): WP_REST_Response {
        $files = SitemapGenerator::generate();

        $described = [];
        foreach ($files as $path => $contents) {
            $is_xml = substr($path, -4) === '.xml';
            $described[] = [
                'path'     => $path,
                'type'     => $is_xml ? (SitemapParser::is_index($contents) ? 'index' : 'urlset') : 'text',
                'locCount' => $is_xml ? count(SitemapParser::locs($contents)) : 0,
                'bytes'    => strlen($contents),
                'contents' => $contents,
            ];
        }

        $parsed = SitemapParser::collect_set($files);

        return new WP_REST_Response([
            'files'      => $described,
            'parsedUrls' => $parsed,
            'parsedCount' => count($parsed),
        ]);
    }
}
