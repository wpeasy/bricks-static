<?php
/**
 * Plugin updater for FluentCart licensing.
 *
 * Hooks into WordPress update system to check for and deliver
 * plugin updates via the FluentCart API.
 *
 * @package WPEasy\BricksStaticPro\Licensing
 * @since   0.3.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Licensing;

defined('ABSPATH') || exit;

/**
 * Plugin updater class.
 *
 * @since 0.3.0
 */
class PluginUpdater {

    /**
     * Transient cache key for version info.
     */
    private string $cacheKey;

    /**
     * Updater configuration.
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Initialize the updater.
     *
     * @since 0.3.0
     *
     * @param array<string, mixed> $config Updater configuration.
     */
    public function __construct(array $config = []) {
        $defaults = [
            'type'                 => 'plugin',
            'slug'                 => '',
            'item_id'              => '',
            'basename'             => '',
            'version'              => '',
            'api_url'              => '',
            'license_key'          => '',
            'license_key_callback' => '',
        ];

        $this->config   = wp_parse_args($config, $defaults);
        $this->cacheKey = 'fsl_' . md5($this->config['basename'] . '_' . $this->config['item_id']) . '_version_info';

        if ($this->config['type'] === 'plugin') {
            $this->initPluginUpdaterHooks();
        }
    }

    /**
     * Register plugin update hooks.
     *
     * @since 0.3.0
     */
    private function initPluginUpdaterHooks(): void {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkPluginUpdate']);
        add_filter('plugins_api', [$this, 'pluginsApiFilter'], 10, 3);
        // SUPPLY-CHAIN HARDENING: on requests to the licensing-API host, reject
        // unsafe (private/loopback) URLs and pin the NON-download API calls to
        // redirection=0 (the version-info POST should never redirect, so a
        // redirect there is suspicious). The package DOWNLOAD must still follow
        // redirects — the FluentCart `download_license_package` endpoint 302s to
        // the actual ZIP (signed / CDN URL) — so it's exempted (detected via
        // download_url()'s `stream => true`). isPackageUrlSafe() still pins the
        // initial package URL to https + the API host before the download runs.
        add_filter('http_request_args', [$this, 'hardenApiHostRequest'], 10, 2);
    }

    /**
     * Force redirection=0 (and unsafe-URL rejection) on any HTTP request
     * aimed at the licensing-API host. Host-scoped so it never affects
     * unrelated requests. See initPluginUpdaterHooks() for the rationale.
     *
     * @param array<string, mixed> $args Parsed request args.
     * @param string               $url  Request URL.
     * @return array<string, mixed> Modified args.
     */
    public function hardenApiHostRequest(array $args, string $url): array {
        $apiHost = wp_parse_url((string) $this->config['api_url'], PHP_URL_HOST);
        $reqHost = wp_parse_url($url, PHP_URL_HOST);
        if (is_string($apiHost) && is_string($reqHost) && $apiHost !== '' && $reqHost !== ''
            && self::canonicalizeHost($reqHost) === self::canonicalizeHost($apiHost)) {
            // SSRF guard on every API-host request.
            $args['reject_unsafe_urls'] = true;
            // Pin redirection=0 only for the non-streamed API calls (version
            // info POST). The package download (download_url → stream=true)
            // must follow the FluentCart endpoint's 302 to the real ZIP, so
            // leave its redirection at WP's default.
            if (empty($args['stream'])) {
                $args['redirection'] = 0;
            }
        }
        return $args;
    }

    /**
     * Check for plugin updates.
     *
     * @since 0.3.0
     *
     * @param object $transientData Update transient data.
     * @return object Modified transient data.
     */
    public function checkPluginUpdate(object $transientData): object {
        /** @var string $pagenow */
        global $pagenow;

        if ('plugins.php' === $pagenow && is_multisite()) {
            return $transientData;
        }

        // Block updates when license is expired (features still work, updates don't)
        if (!LicenseEnforcer::getInstance()->canUpdate()) {
            return $transientData;
        }

        // Always run our own version check. Previously we returned early if
        // any other plugin had already populated our basename in
        // $transientData->response — but that allowed a co-resident plugin
        // (or one that lands code via a separate vuln) to pre-set our basename
        // with a stale/zeroed entry and silently suppress our updates. Now
        // we check the remote unconditionally and only refuse to overwrite
        // when an existing entry is already at-or-above the version we'd offer.
        $versionInfo = $this->getVersionInfo();

        if (false !== $versionInfo && is_object($versionInfo) && isset($versionInfo->new_version)) {
            unset($versionInfo->sections);

            if (version_compare($this->config['version'], $versionInfo->new_version, '<')) {
                // Don't downgrade an existing entry that already advertises a
                // newer-or-equal version (e.g. another auto-updater handler).
                $existing = $transientData->response[$this->config['basename']] ?? null;
                if (is_object($existing) && isset($existing->new_version)
                    && version_compare($existing->new_version, $versionInfo->new_version, '>=')) {
                    // Existing entry wins — leave it alone.
                } else {
                    $transientData->response[$this->config['basename']] = $versionInfo;
                }
            } else {
                $transientData->no_update[$this->config['basename']] = $versionInfo;
            }

            $transientData->last_checked                          = time();
            $transientData->checked[$this->config['basename']]    = $this->config['version'];
        }

        return $transientData;
    }

    /**
     * Filter the plugins API for this plugin's information.
     *
     * @since 0.3.0
     *
     * @param mixed       $data   Plugin data.
     * @param string      $action API action.
     * @param object|null $args   Request arguments.
     * @return mixed
     */
    public function pluginsApiFilter(mixed $data, string $action = '', ?object $args = null): mixed {
        if ('plugin_information' !== $action || !$args) {
            return $data;
        }

        $slug = $this->config['slug'];

        if (!isset($args->slug) || $args->slug !== $slug) {
            return $data;
        }

        $data = $this->getVersionInfo();

        if (is_wp_error($data)) {
            return $data;
        }

        if (!$data) {
            return new \WP_Error('no_data', 'No data found for this plugin.');
        }

        return $data;
    }

    /**
     * Get cached version info.
     *
     * @since 0.3.0
     *
     * @return mixed Cached version info or false.
     */
    private function getCachedVersionInfo(): mixed {
        /** @var string $pagenow */
        global $pagenow;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache-bust check of a core admin screen's query var; no form processing or state change.
        if ('update-core.php' === $pagenow || ($pagenow === 'plugin-install.php' && !empty($_GET['plugin']))) {
            return false;
        }

        return get_transient($this->cacheKey);
    }

    /**
     * Cache version info.
     *
     * @since 0.3.0
     *
     * @param mixed $value Version info to cache.
     */
    private function setCachedVersionInfo(mixed $value): void {
        if (!$value) {
            return;
        }

        set_transient($this->cacheKey, $value, 3 * HOUR_IN_SECONDS);
    }

    /**
     * Get version info (cached or remote).
     *
     * @since 0.3.0
     *
     * @return mixed Version info object or false.
     */
    private function getVersionInfo(): mixed {
        $versionInfo = $this->getCachedVersionInfo();

        if (false === $versionInfo) {
            $versionInfo = $this->getRemoteVersionInfo();
            $this->setCachedVersionInfo($versionInfo);
        }

        return $versionInfo;
    }

    /**
     * Fetch version info from the remote API.
     *
     * @since 0.3.0
     *
     * @return object|false Version info object or false on failure.
     */
    private function getRemoteVersionInfo(): object|false {
        // SECURITY: refuse to send the license key over plaintext HTTP. WP
        // verifies TLS only when the scheme is https; an http:// api_url
        // (misconfigured staging, accidental filter override) would leak the
        // long-lived secret on the wire.
        $apiScheme = strtolower((string) wp_parse_url((string) $this->config['api_url'], PHP_URL_SCHEME));
        if ($apiScheme !== 'https') {
            return false;
        }

        $fullUrl = add_query_arg(['fluent-cart' => 'get_license_version'], $this->config['api_url']);

        $payload = [
            'item_id'          => $this->config['item_id'],
            'current_version'  => $this->config['version'],
            'site_url'         => home_url(),
            'platform_version' => get_bloginfo('version'),
            'server_version'   => phpversion(),
            'license_key'      => $this->config['license_key'],
        ];

        if (empty($payload['license_key']) && !empty($this->config['license_key_callback'])) {
            // SECURITY: is_callable check before invoking. The callback
            // value travels through $this->config which gets passed into
            // apply_filters() further down — a misregistered or hijacked
            // value would otherwise hit call_user_func() with no contract.
            $cb = $this->config['license_key_callback'];
            if (is_callable($cb)) {
                $payload['license_key'] = (string) call_user_func($cb);
            }
        }

        /** @var array<string, mixed> $payload */
        $payload = apply_filters('fluent_sl/updater_payload_' . $this->config['slug'], $payload, $this->config);

        $response = wp_remote_post($fullUrl, [
            'timeout'     => 15,
            'redirection' => 0, // never follow redirects off the API host (also enforced by hardenApiHostRequest)
            'body'        => $payload,
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $responseBody = wp_remote_retrieve_body($response);

        if (empty($responseBody)) {
            return false;
        }

        $versionInfo = json_decode($responseBody);

        if (null === $versionInfo || !is_object($versionInfo) || !isset($versionInfo->new_version)) {
            return false;
        }

        // SUPPLY-CHAIN HARDENING: validate the `package` URL before surfacing
        // it to WordPress's auto-updater. WP core will download and install
        // whatever ZIP is at this URL — no allow-list, no signature check —
        // so a compromised licensing API (or any CDN edge in front of it)
        // could otherwise return a malicious package URL and ship attacker
        // PHP to every install on the next update tick.
        //
        // Guard: scheme must be HTTPS, host must equal the API host. Paired
        // with hardenApiHostRequest() (redirection=0 on the API host) so the
        // pin can't be bypassed by a same-host package URL that redirects
        // off-host at download time.
        // TODO: also verify a signed payload (Ed25519) once the licensing
        // server supports it — a signature is the only thing that protects
        // against an origin compromise serving a malicious ZIP from the
        // pinned host itself. Until then, host pinning + no-redirects is the
        // best available mitigation.
        if (!self::isPackageUrlSafe($versionInfo->package ?? '', $this->config['api_url'])) {
            return false;
        }

        $versionInfo->plugin = $this->config['basename'];
        $versionInfo->slug   = $this->config['slug'];

        if (!empty($versionInfo->sections)) {
            $versionInfo->sections = (array) $versionInfo->sections;
        }

        $versionInfo->banners = isset($versionInfo->banners) ? (array) $versionInfo->banners : [];
        $versionInfo->icons   = isset($versionInfo->icons) ? (array) $versionInfo->icons : [];

        return $versionInfo;
    }

    /**
     * Validate that an update package URL is safe to surface to WP's
     * auto-updater. The package MUST be HTTPS and live on the same host
     * as the licensing API — anything else is rejected.
     *
     * @param mixed  $packageUrl Package URL from the licensing API response.
     * @param string $apiUrl     The configured licensing API base URL.
     * @return bool True if safe, false otherwise.
     */
    private static function isPackageUrlSafe($packageUrl, string $apiUrl): bool {
        if (!is_string($packageUrl) || $packageUrl === '') {
            return false;
        }
        $pkg = wp_parse_url($packageUrl);
        $api = wp_parse_url($apiUrl);
        if (!is_array($pkg) || !is_array($api)) {
            return false;
        }
        $pkgScheme = isset($pkg['scheme']) ? strtolower($pkg['scheme']) : '';
        if ($pkgScheme !== 'https') {
            return false;
        }
        $pkgHost = isset($pkg['host']) ? self::canonicalizeHost($pkg['host']) : '';
        $apiHost = isset($api['host']) ? self::canonicalizeHost($api['host']) : '';
        if ($pkgHost === '' || $apiHost === '' || $pkgHost !== $apiHost) {
            return false;
        }
        return true;
    }

    /**
     * Canonicalize a hostname for strict-equality comparison.
     *
     * Why: a naive `strtolower()` + `===` allows several bypasses:
     *   - trailing-dot FQDN (`brxprod.com.` vs `brxprod.com`) — both
     *     resolve to the same DNS records, but `===` says they differ
     *   - punycode/IDN forms (`xn--brxprod-...` vs the unicode form) —
     *     same DNS host, different string representation
     *   - uppercase letters (`BRXPROD.COM`)
     *
     * If we let any of these slip past the host-pin, an attacker who can
     * influence the licensing API response (XSS / CRLF / rogue admin / DB
     * injection on the licensing app) can return a package URL on a host
     * that matches DNS but bypasses the comparison.
     */
    private static function canonicalizeHost(string $host): string {
        $host = trim($host, '[]');           // strip IPv6 brackets if present
        $host = rtrim($host, '.');           // strip trailing FQDN dot
        $host = strtolower($host);
        if (function_exists('idn_to_ascii')) {
            // INTL_IDNA_VARIANT_UTS46 is the modern variant; fall back to
            // the original on PHP installs without it.
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii   = @idn_to_ascii($host, IDNA_DEFAULT, $variant);
            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }
        return $host;
    }
}
