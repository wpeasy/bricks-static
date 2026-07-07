<?php
/**
 * Loopback page/asset renderer.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

use WPEasy\BricksStatic\Support\UrlSafety;

defined('ABSPATH') || exit;

/**
 * Fetches the final rendered output of a URL via an internal HTTP request.
 *
 * Builder-agnostic: it captures whatever the site outputs (Bricks, Gutenberg,
 * etc.). SSL verification is relaxed for local/dev hosts (self-signed certs) so
 * loopback requests to *.local / *.test / localhost succeed; override via the
 * `bs_render_sslverify` and `bs_render_request_args` filters.
 */
final class PageRenderer {

    /**
     * User-agent prefix sent on every loopback request, so the site can tell a
     * static-render request from a real visitor (see is_render_request()).
     */
    public const USER_AGENT_PREFIX = 'BricksStatic/';

    /**
     * Whether the CURRENT request is one of our loopback render requests.
     * Lets render-time hooks (e.g. stripping the emoji loader) apply only to the
     * static output, never to the live front end.
     */
    public static function is_render_request(): bool {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return strpos($ua, self::USER_AGENT_PREFIX) === 0;
    }

    /**
     * Fetch a page URL and return its rendered HTML.
     *
     * @param string $url Absolute URL.
     * @return array{code:int,body:string,contentType:string}
     * @throws \RuntimeException On transport error.
     */
    public static function render(string $url): array {
        self::release_session();
        $response = wp_remote_get($url, self::request_args($url));

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        return [
            'code'        => (int) wp_remote_retrieve_response_code($response),
            'body'        => (string) wp_remote_retrieve_body($response),
            'contentType' => (string) wp_remote_retrieve_header($response, 'content-type'),
        ];
    }

    /**
     * Fetch a binary asset URL.
     *
     * @param string $url Absolute URL.
     * @return array{code:int,body:string,contentType:string}
     * @throws \RuntimeException On transport error.
     */
    public static function fetch_asset(string $url): array {
        self::release_session();
        $args = self::request_args($url);
        /**
         * Filters the per-asset fetch timeout (seconds). Raise it on slow hosts
         * that serve large/generated assets; keep it below the sync watchdog
         * window ({@see bs_stale_seconds}) so an in-flight fetch isn't reaped.
         *
         * @param int    $timeout Seconds.
         * @param string $url     Asset URL.
         */
        $args['timeout'] = max(5, (int) apply_filters('bs_asset_timeout', 60, $url));

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        return [
            'code'        => (int) wp_remote_retrieve_response_code($response),
            'body'        => (string) wp_remote_retrieve_body($response),
            'contentType' => (string) wp_remote_retrieve_header($response, 'content-type'),
        ];
    }

    /**
     * Build the HTTP request args used for loopback requests.
     *
     * @param string $url The URL being requested (for filters).
     * @return array<string,mixed>
     */
    public static function request_args(string $url = ''): array {
        $args = [
            /**
             * Filters the per-page loopback render timeout (seconds). Raise it for
             * heavy pages; keep it below the sync watchdog window
             * ({@see bs_stale_seconds}) so an in-flight render isn't reaped.
             *
             * @param int    $timeout Seconds.
             * @param string $url     Page URL.
             */
            'timeout'     => max(5, (int) apply_filters('bs_render_timeout', 60, $url)),
            'redirection' => 5,
            'sslverify'   => self::should_verify_ssl(),
            'user-agent'  => self::USER_AGENT_PREFIX . BS_VERSION,
            'headers'     => [],
        ];

        /**
         * Filters the loopback HTTP request args (auth headers, timeout, etc.).
         *
         * @param array<string,mixed> $args Request args.
         * @param string              $url  Requested URL.
         */
        return (array) apply_filters('bs_render_request_args', $args, $url);
    }

    /**
     * Release the PHP session lock before a loopback request, so the rendered
     * page (a request to the same site) isn't blocked waiting for our session.
     */
    private static function release_session(): void {
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
    }

    /**
     * Whether to verify TLS for loopback requests. Relaxed for local/dev hosts.
     */
    private static function should_verify_ssl(): bool {
        $is_local    = in_array(wp_get_environment_type(), ['local', 'development'], true);
        // Exact dev-host match (not a prefix), so a public host that merely starts
        // with "127.0.0.1" isn't exempted from TLS verification.
        $is_dev_host = UrlSafety::is_dev_host((string) wp_parse_url(home_url(), PHP_URL_HOST));

        $verify = !($is_local || $is_dev_host);

        /**
         * Filters whether loopback requests verify TLS certificates.
         *
         * @param bool $verify Whether to verify.
         */
        return (bool) apply_filters('bs_render_sslverify', $verify);
    }
}
