<?php
/**
 * Destination web-server probe: detect the server and verify our rules took effect.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.0
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Sync;

use WPEasy\ShipStatic\Deploy\ConnectorClient;
use WPEasy\ShipStatic\Deploy\PackageDeployer;
use WPEasy\ShipStatic\Settings\Destination;
use WPEasy\ShipStatic\Settings\Destinations;
use WPEasy\ShipStatic\Support\UrlSafety;

defined('ABSPATH') || exit;

/**
 * Probes a destination's public URL over HTTP and reports (a) which web server
 * answers, (b) whether the HTML is gzip-served, and (c) whether the cache
 * policy from `HtaccessBuilder` is actually in effect — one HTML fetch plus one
 * asset HEAD.
 *
 * This is the "verify" half of server config. Apache/LiteSpeed pick the rules
 * up from the uploaded .htaccess; nginx has no per-directory config, so there
 * the rules are a manual paste and this is the only way to confirm they landed.
 * Stock nginx already serves the static output without them (index.html and
 * directory resolution are defaults), so an unapplied result on nginx is an
 * "optional tuning missing" notice, not a broken deploy.
 *
 * Read-only against the destination and synchronous (two short requests); the
 * result is cached per destination so the dashboard can show the last check
 * without re-probing on every load.
 */
final class ServerProbe {

    /**
     * Option prefix for the last result, per destination id.
     */
    private const OPTION_PREFIX = 'shipstatic_server_check_';

    /**
     * Per-request timeout (seconds).
     */
    private const TIMEOUT = 15;

    /**
     * Cap on the fetched HTML body — only the headers matter.
     */
    private const BODY_LIMIT = 262144;

    /**
     * Assets are "long-cached" if max-age is at least this (30 days) even
     * without `immutable`, so a host's own sane policy counts as applied.
     */
    private const LONG_MAX_AGE = 2592000;

    /**
     * Run the probe for a destination and remember the result.
     *
     * @param string $dest_id Destination id.
     * @return array<string,mixed> Result (see `empty_result()` for the shape).
     */
    public static function run(string $dest_id): array {
        $dest   = Destinations::get($dest_id);
        $result = self::empty_result($dest_id);

        if ($dest === null) {
            $result['error'] = __('Unknown destination.', 'shipstatic');
            return $result;
        }

        $base = rtrim(PackageDeployer::base_url($dest), '/');
        if ($base === '') {
            $result['error'] = __('Set a Destination URL for this destination first, so the probe knows where the site is served from.', 'shipstatic');
            return self::remember($dest_id, $result);
        }

        $result['url']        = $base . '/';
        $result['urlGuessed'] = PackageDeployer::url_is_guess($dest);

        $pushed           = Manifest::load(Destinations::pushed_option($dest_id));
        $result['synced'] = $pushed !== [];
        $asset_path       = self::pick_asset($pushed);

        // 1. The home page, asking for gzip so we can see whether it's served
        //    compressed (pre-compressed or on-the-fly — the headers don't say
        //    which, and for the visitor it doesn't matter).
        $html = self::fetch($base . '/', 'GET', ['Accept-Encoding' => 'gzip']);
        if (is_wp_error($html)) {
            $result['error'] = $html->get_error_message();
            return self::remember($dest_id, $result);
        }

        $result['ok']           = true;
        $result['serverHeader'] = self::header($html, 'server');
        $result['server']       = self::classify($result['serverHeader']);

        // A Connector destination can tell us its origin server exactly (PHP's
        // SERVER_SOFTWARE), which beats a stripped or CDN-masked Server header.
        $origin = self::origin_via_connector($dest);
        if ($origin !== '') {
            $result['serverHeader'] = $origin;
            $result['server']       = self::classify($origin);
            $result['viaConnector'] = true;
        }

        // A Cloudflare destination IS the edge (there's no origin server behind
        // it to look for), and its cache rules are the `_headers` we publish —
        // so report it as Cloudflare rather than "a CDN hiding the origin".
        if ($dest->is_cloudflare()) {
            $result['server'] = 'cloudflare';
        }
        // GitHub Pages likewise: its own servers, its own fixed cache policy.
        if ($dest->is_github()) {
            $result['server'] = 'github';
        }

        $cc = self::header($html, 'cache-control');

        $result['html'] = [
            'code'         => (int) wp_remote_retrieve_response_code($html),
            'gzip'         => str_contains(strtolower(self::header($html, 'content-encoding')), 'gzip'),
            'cacheControl' => $cc,
            'revalidate'   => self::is_revalidate($cc),
        ];

        // 2. One long-cache asset the destination actually has (from its
        //    pushed manifest) — HEAD, falling back to GET where HEAD is refused.
        if ($asset_path !== '') {
            $asset_url = $base . '/' . implode('/', array_map('rawurlencode', explode('/', $asset_path)));
            $asset     = self::fetch($asset_url, 'HEAD');
            $code      = is_wp_error($asset) ? 0 : (int) wp_remote_retrieve_response_code($asset);
            if (is_wp_error($asset) || $code === 405 || $code === 501) {
                $asset = self::fetch($asset_url, 'GET');
                $code  = is_wp_error($asset) ? 0 : (int) wp_remote_retrieve_response_code($asset);
            }
            if (!is_wp_error($asset)) {
                $acc             = self::header($asset, 'cache-control');
                $result['asset'] = [
                    'path'         => $asset_path,
                    'code'         => $code,
                    'cacheControl' => $acc,
                    'immutable'    => self::is_long_cache($acc),
                ];
            }
        }

        $result['applied'] = self::applied($result);
        $result['message'] = self::message($result);

        return self::remember($dest_id, $result);
    }

    /**
     * SERVER_SOFTWARE as reported by the destination's Connector receiver, or
     * '' when this isn't a paired Connector destination / it doesn't answer.
     * Best-effort: a probe must never fail because of it.
     *
     * @param Destination $dest Destination.
     */
    private static function origin_via_connector(Destination $dest): string {
        if (!$dest->is_connector()) {
            return '';
        }
        $c      = $dest->connection_config();
        $client = new ConnectorClient((string) $c['url'], (string) $c['connectorId'], (string) $c['connectorSecret']);
        if (!$client->configured()) {
            return '';
        }
        try {
            return trim((string) ($client->ping()['server'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The last stored result for a destination, if any.
     *
     * @param string $dest_id Destination id.
     * @return array<string,mixed>|null
     */
    public static function last(string $dest_id): ?array {
        $value = get_option(self::OPTION_PREFIX . $dest_id);

        return is_array($value) && isset($value['checkedAt']) ? $value : null;
    }

    /**
     * Last results for every visible destination, keyed by id.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all_last(): array {
        $out = [];
        foreach (Destinations::visible_objects() as $dest) {
            $last = self::last($dest->id());
            if ($last !== null) {
                $out[$dest->id()] = $last;
            }
        }

        return $out;
    }

    /**
     * Drop the stored result for a destination (on removal / re-target).
     *
     * @param string $dest_id Destination id.
     */
    public static function forget(string $dest_id): void {
        delete_option(self::OPTION_PREFIX . $dest_id);
    }

    /**
     * Result skeleton.
     *
     * @param string $dest_id Destination id.
     * @return array<string,mixed>
     */
    private static function empty_result(string $dest_id): array {
        return [
            'destId'       => $dest_id,
            'ok'           => false,
            'error'        => '',
            'url'          => '',
            'urlGuessed'   => false,
            'synced'       => false,
            'server'       => 'unknown',
            'serverHeader' => '',
            'html'         => null,
            'asset'        => null,
            'applied'      => 'unknown',
            'message'      => '',
            'checkedAt'    => time(),
        ];
    }

    /**
     * Store and return a result.
     *
     * @param string              $dest_id Destination id.
     * @param array<string,mixed> $result  Result.
     * @return array<string,mixed>
     */
    private static function remember(string $dest_id, array $result): array {
        $result['checkedAt'] = time();
        update_option(self::OPTION_PREFIX . $dest_id, $result, false);

        return $result;
    }

    /**
     * Guarded fetch of a destination URL. Redirects are refused by `UrlSafety`
     * (a destination URL that 301s — e.g. http→https or a missing trailing
     * slash — surfaces as an error the user can fix in the URL field).
     *
     * @param string               $url     URL.
     * @param string               $method  GET or HEAD.
     * @param array<string,string> $headers Extra request headers.
     * @return array<mixed>|\WP_Error
     */
    private static function fetch(string $url, string $method, array $headers = []) {
        return UrlSafety::guarded_get($url, [
            'method'              => $method,
            'timeout'             => self::TIMEOUT,
            'headers'             => $headers,
            // We only read headers; leave the body compressed and small.
            'decompress'          => false,
            'limit_response_size' => self::BODY_LIMIT,
            'sslverify'           => PackageDeployer::sslverify($url),
        ]);
    }

    /**
     * A response header as one string (multi-value headers joined).
     *
     * @param array<mixed> $response Response.
     * @param string       $name     Header name.
     */
    private static function header(array $response, string $name): string {
        $value = wp_remote_retrieve_header($response, $name);
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        return trim((string) $value);
    }

    /**
     * Classify a `Server:` header. A CDN/proxy in front of the origin masks the
     * real server, so that is reported as its own kind rather than "other".
     *
     * @param string $header Raw Server header.
     * @return string apache|litespeed|nginx|proxied|other|unknown (`cloudflare`
     *                / `github` are set by run() for those destinations, never here)
     */
    private static function classify(string $header): string {
        $h = strtolower($header);
        if ($h === '') {
            return 'unknown';
        }
        if (str_contains($h, 'litespeed')) {
            return 'litespeed';
        }
        if (str_contains($h, 'apache')) {
            return 'apache';
        }
        if (str_contains($h, 'nginx') || str_contains($h, 'openresty')) {
            return 'nginx';
        }
        foreach (['cloudflare', 'cloudfront', 'varnish', 'fastly', 'akamai', 'bunny', 'netlify', 'vercel'] as $proxy) {
            if (str_contains($h, $proxy)) {
                return 'proxied';
            }
        }

        return 'other';
    }

    /**
     * Whether an HTML Cache-Control matches our "always revalidate" policy.
     *
     * @param string $cc Cache-Control value.
     */
    private static function is_revalidate(string $cc): bool {
        $cc = strtolower($cc);

        return str_contains($cc, 'must-revalidate')
            || str_contains($cc, 'no-cache')
            || (bool) preg_match('/max-age=0\b/', $cc);
    }

    /**
     * Whether an asset Cache-Control is a long-cache policy.
     *
     * @param string $cc Cache-Control value.
     */
    private static function is_long_cache(string $cc): bool {
        $cc = strtolower($cc);
        if (str_contains($cc, 'immutable')) {
            return true;
        }
        if (preg_match('/max-age=(\d+)/', $cc, $m)) {
            return (int) $m[1] >= self::LONG_MAX_AGE;
        }

        return false;
    }

    /**
     * Pick one pushed asset to probe: prefer CSS, then JS, then any long-cache
     * extension, so the check exercises a file our rules mark immutable.
     *
     * @param array<string,array{size:int,hash:string}> $pushed Pushed manifest.
     * @return string Relative path, or '' when nothing suitable was pushed.
     */
    private static function pick_asset(array $pushed): string {
        $paths = array_keys($pushed);
        foreach (['css', 'js'] as $ext) {
            foreach ($paths as $path) {
                if (preg_match('/\.' . $ext . '$/i', $path)) {
                    return $path;
                }
            }
        }
        $pattern = '/\.(' . implode('|', HtaccessBuilder::asset_extensions()) . ')$/i';
        foreach ($paths as $path) {
            if (preg_match($pattern, $path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * Overall verdict on the cache policy.
     *
     * @param array<string,mixed> $r Result so far.
     * @return string yes|partial|no|unknown
     */
    private static function applied(array $r): string {
        if (!is_array($r['html']) || (int) $r['html']['code'] !== 200) {
            return 'unknown';
        }
        $html_ok  = (bool) $r['html']['revalidate'];
        $asset_ok = is_array($r['asset']) && (int) $r['asset']['code'] === 200 && (bool) $r['asset']['immutable'];

        if ($html_ok && $asset_ok) {
            return 'yes';
        }
        if (!is_array($r['asset'])) {
            // No pushed asset to test against — only the HTML half is known.
            return $html_ok ? 'partial' : 'no';
        }

        return $html_ok || $asset_ok ? 'partial' : 'no';
    }

    /**
     * Human summary of a result.
     *
     * @param array<string,mixed> $r Result.
     */
    private static function message(array $r): string {
        $labels = [
            'apache'    => 'Apache',
            'litespeed' => 'LiteSpeed',
            'nginx'     => 'nginx',
            'cloudflare'=> 'Cloudflare',
            'github'    => 'GitHub Pages',
            'proxied'   => $r['serverHeader'],
            'other'     => $r['serverHeader'],
            'unknown'   => __('an unidentified server', 'shipstatic'),
        ];
        $server = (string) ($labels[$r['server']] ?? $r['serverHeader']);
        $code   = is_array($r['html']) ? (int) $r['html']['code'] : 0;

        if ($code !== 200) {
            /* translators: %d: HTTP status code */
            return sprintf(__('The destination URL answered HTTP %d, so the rules could not be verified. Check the Destination URL and remote path point at the deployed site.', 'shipstatic'), $code);
        }

        if (!$r['synced']) {
            /* translators: %s: web server name */
            return sprintf(__('%s is answering, but nothing has been synced to this destination yet — run a Sync, then check again.', 'shipstatic'), $server);
        }

        if ($r['server'] === 'github') {
            // GitHub Pages serves everything with its own short cache
            // (max-age=600) and offers no way to set headers — nothing to fix.
            return __('GitHub Pages — the site is being served. GitHub sets its own cache headers (about 10 minutes for every file) and compresses files itself; custom cache rules can’t be applied there.', 'shipstatic');
        }

        switch ($r['applied']) {
            case 'yes':
                /* translators: %s: web server name */
                return sprintf(__('%s — cache policy in effect (assets long-cached, HTML revalidated).', 'shipstatic'), $server);

            case 'partial':
                /* translators: %s: web server name */
                return sprintf(__('%s — the cache policy is only partly in effect. Compare the headers below with the rules.', 'shipstatic'), $server);
        }

        switch ($r['server']) {
            case 'cloudflare':
                return __('Cloudflare — the cache rules are not in effect yet. They are published with every Cloudflare deploy, so run a Sync, then check again.', 'shipstatic');

            case 'nginx':
                return __('nginx detected. The site is being served, but the optional cache tuning is not applied — paste the nginx snippet into your server block and reload nginx, then check again.', 'shipstatic');

            case 'apache':
            case 'litespeed':
                /* translators: %s: web server name */
                return sprintf(__('%s detected, but the .htaccess cache headers are not in effect. Check that mod_headers is enabled and that .htaccess overrides are allowed (AllowOverride) for the destination directory.', 'shipstatic'), $server);

            case 'proxied':
                /* translators: %s: proxy/CDN name */
                return sprintf(__('A CDN/proxy (%s) is in front of the destination, which hides the origin server and may rewrite cache headers. The cache policy is not visible at the edge.', 'shipstatic'), $server);
        }

        /* translators: %s: web server name */
        return sprintf(__('%s detected — the cache policy is not in effect. On Apache/LiteSpeed the .htaccess is uploaded automatically; on nginx, apply the snippet below.', 'shipstatic'), $server);
    }
}
