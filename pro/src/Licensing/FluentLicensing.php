<?php
/**
 * FluentCart licensing integration.
 *
 * Handles license activation, deactivation, and status checks
 * via the FluentCart licensing API.
 *
 * @package WPEasy\BricksStaticPro\Licensing
 * @since   0.3.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Licensing;

defined('ABSPATH') || exit;

/**
 * Core licensing class using FluentCart API.
 *
 * @since 0.3.0
 */
class FluentLicensing {

    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Licensing configuration.
     *
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * WordPress option key for storing license data.
     */
    public string $settingsKey = '';

    /**
     * Register the licensing system with configuration.
     *
     * @since 0.3.0
     *
     * @param array<string, mixed> $config Licensing configuration.
     * @return self
     *
     * @throws \Exception If required configuration is missing.
     */
    public function register(array $config = []): self {
        if (self::$instance) {
            return self::$instance;
        }

        if (empty($config['basename']) || empty($config['version']) || empty($config['api_url'])) {
            throw new \Exception('Invalid configuration for FluentLicensing. Provide basename, version, and api_url.');
        }

        $this->config = $config;
        $this->config['slug'] = (string) ($config['slug'] ?? explode('/', $config['basename'])[0]);
        $this->settingsKey = $config['settings_key'] ?? '__' . $this->config['slug'] . '_sl_info';

        $updaterConfig = $this->config;

        if (empty($updaterConfig['license_key']) && empty($updaterConfig['license_key_callback'])) {
            $updaterConfig['license_key_callback'] = fn(): string => $this->getCurrentLicenseKey();
        }

        new PluginUpdater($updaterConfig);

        self::$instance = $this;

        return self::$instance;
    }

    /**
     * Get a configuration value.
     *
     * @since 0.3.0
     *
     * @param string $key Configuration key.
     * @return mixed
     *
     * @throws \Exception If key does not exist.
     */
    public function getConfig(string $key): mixed {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        throw new \Exception("Configuration key '{$key}' does not exist.");
    }

    /**
     * Get the singleton instance.
     *
     * @since 0.3.0
     *
     * @return self
     *
     * @throws \Exception If not yet registered.
     */
    public static function getInstance(): self {
        if (!self::$instance) {
            throw new \Exception('Licensing is not registered. Call register() first.');
        }

        return self::$instance;
    }

    /**
     * Activate a license key.
     *
     * @since 0.3.0
     *
     * @param string $licenseKey The license key to activate.
     * @return array<string, mixed>|\WP_Error Activation result or error.
     */
    public function activate(string $licenseKey = ''): array|\WP_Error {
        if (!$licenseKey) {
            return new \WP_Error('license_key_missing', 'License key is required for activation.');
        }

        $response = $this->apiRequest('activate_license', [
            'license_key' => $licenseKey,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // SECURITY: encrypt the license key at rest using the same policy
        // as AI provider keys (AES-256-CBC keyed off AUTH_KEY). If
        // encryption is unsafe (missing AUTH_KEY / OpenSSL), refuse the
        // save rather than persisting plaintext — surfacing an actionable
        // error is better than silently leaking a long-lived secret into
        // wp_options where any DB dump or support tarball exposes it.
        $encryptedKey = \WPEasy\BricksStatic\Support\Crypto::encrypt($licenseKey);
        if ($encryptedKey === '') {
            return new \WP_Error(
                'license_encryption_unavailable',
                __('Cannot store license safely: AUTH_KEY in wp-config.php must be set (≥ 32 chars) and the OpenSSL PHP extension must be available.', 'bricks-static-pro')
            );
        }

        $saveData = [
            'license_key'        => $encryptedKey,
            'license_encrypted'  => true,
            'status'             => $response['status'] ?? 'valid',
            'variation_id'       => $response['variation_id'] ?? '',
            'variation_title'    => $response['variation_title'] ?? '',
            'expires'            => $response['expiration_date'] ?? '',
            'activation_hash'    => $response['activation_hash'] ?? '',
        ];

        update_option($this->settingsKey, $saveData, false);

        // Fire action to clear any failure timestamps
        do_action('bsp_license_activated');

        return $saveData;
    }

    /**
     * Deactivate the current license.
     *
     * @since 0.3.0
     *
     * @return array<string, mixed>|\WP_Error Deactivation result or error.
     */
    public function deactivate(): array|\WP_Error {
        $deactivated = $this->apiRequest('deactivate_license', [
            'license_key' => $this->getCurrentLicenseKey(),
        ]);

        delete_option($this->settingsKey);

        return is_wp_error($deactivated) ? $deactivated : $deactivated;
    }

    /**
     * Get the current license status.
     *
     * @since 0.3.0
     *
     * @param bool $remoteFetch Whether to validate against the remote API.
     * @return array<string, mixed>|\WP_Error License status or error.
     */
    public function getStatus(bool $remoteFetch = false): array|\WP_Error {
        $currentLicense = get_option($this->settingsKey, []);
        if (!$currentLicense || !is_array($currentLicense) || empty($currentLicense['license_key'])) {
            return [
                'license_key'     => '',
                'status'          => 'unregistered',
                'variation_id'    => '',
                'variation_title' => '',
                'expires'         => '',
            ];
        }

        // SECURITY: decrypt the stored key at the boundary so all callers
        // see the historical plaintext API. The on-disk form (encrypted)
        // and the in-memory form (plaintext) are kept distinct here.
        if (!empty($currentLicense['license_encrypted'])) {
            $currentLicense['license_key'] = \WPEasy\BricksStatic\Support\Crypto::decrypt(
                (string) $currentLicense['license_key']
            );
            unset($currentLicense['license_encrypted']);
        }

        if (!$remoteFetch) {
            return $currentLicense;
        }

        $remoteStatus = $this->apiRequest('check_license', [
            'license_key'     => (string) $currentLicense['license_key'],
            'activation_hash' => $currentLicense['activation_hash'] ?? '',
            'item_id'         => $this->config['item_id'] ?? '',
            'site_url'        => home_url(),
        ]);

        if (is_wp_error($remoteStatus)) {
            return $remoteStatus;
        }

        // SECURITY: validate `status` against an explicit allowlist before
        // ANY assignment. A hostile API response could otherwise store an
        // arbitrary string (e.g. HTML payload) into wp_options['status']
        // that flows back into admin UI on next page load. Anything not on
        // the allowlist falls back to 'error'.
        $allowedStatuses = ['valid', 'active', 'expired', 'disabled', 'invalid', 'unregistered', 'inactive'];
        $remoteStatusRaw = (string) ($remoteStatus['status'] ?? 'unregistered');
        $status          = in_array($remoteStatusRaw, $allowedStatuses, true) ? $remoteStatusRaw : 'error';
        $errorType       = (string) ($remoteStatus['error_type'] ?? '');

        // SECURITY: sanitise ALL remote fields up front, BEFORE any
        // update_option call. The previous flow sanitised renew_url /
        // error_message AFTER update_option — a future refactor that
        // reordered the block would silently persist unsanitised values.
        // Persisting only the sanitised forms removes that footgun.
        $currentLicense['renew_url']  = esc_url_raw((string) ($remoteStatus['renew_url'] ?? ''));
        $currentLicense['is_expired'] = (bool) ($remoteStatus['is_expired'] ?? false);
        if ($errorType !== '') {
            $currentLicense['error_type']    = sanitize_key($errorType);
            $currentLicense['error_message'] = wp_strip_all_tags((string) ($remoteStatus['message'] ?? ''));
        }

        if (!empty($currentLicense['status'])) {
            $currentLicense['status'] = $status;

            if (!empty($remoteStatus['expiration_date'])) {
                $currentLicense['expires'] = sanitize_text_field($remoteStatus['expiration_date']);
            }

            if (!empty($remoteStatus['variation_id'])) {
                $currentLicense['variation_id'] = sanitize_text_field($remoteStatus['variation_id']);
            }

            if (!empty($remoteStatus['variation_title'])) {
                $currentLicense['variation_title'] = sanitize_text_field($remoteStatus['variation_title']);
            }

            update_option($this->settingsKey, $currentLicense, false);
        } else {
            $currentLicense['status'] = 'error';
        }

        return $currentLicense;
    }

    /**
     * Get the current license key.
     *
     * @since 0.3.0
     *
     * @return string The active license key, or empty string.
     */
    public function getCurrentLicenseKey(): string {
        // getStatus() decrypts at the boundary, so the returned license_key
        // is plaintext (or empty) for both legacy and v0.1.95+ records.
        $status = $this->getStatus();
        return is_array($status) ? (string) ($status['license_key'] ?? '') : '';
    }

    /**
     * Check if the current site is a local/dev environment.
     *
     * @since 0.3.0
     *
     * @return bool
     */
    public static function isLocalDevSite(): bool {
        $host = wp_parse_url(get_site_url(), PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        // Check local TLDs.
        $localTlds = ['.local', '.test', '.dev', '.invalid', '.localhost'];
        foreach ($localTlds as $tld) {
            if (str_ends_with($host, $tld)) {
                return true;
            }
        }

        // Check private/reserved IP ranges.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        // Check common dev keywords — match at DNS-label boundaries only.
        // Substring matching previously let `mydevtools.com`, `wpeasydev.com`,
        // `production-dev-tools.com` etc. masquerade as local dev sites and
        // skip license-activation slot consumption.
        if (preg_match('/(?:^|\.)(?:localhost|staging|dev|development)(?:\.|$)/i', $host)) {
            return true;
        }

        // Check WP constant.
        if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            return true;
        }

        return false;
    }

    /**
     * Make an API request to the FluentCart licensing server.
     *
     * @since 0.3.0
     *
     * @param string               $action API action.
     * @param array<string, mixed> $data   Request data.
     * @return array<string, mixed>|\WP_Error Response data or error.
     */
    private function apiRequest(string $action, array $data = []): array|\WP_Error {
        $url = $this->config['api_url'];

        // SECURITY: refuse non-HTTPS. The license key is a long-lived
        // secret that travels in the request body — over plaintext HTTP it
        // would be exposed to any on-path observer (corporate proxies,
        // shared-WiFi attackers, etc.). WordPress's TLS verification only
        // applies when the scheme is https.
        $scheme = strtolower((string) wp_parse_url((string) $url, PHP_URL_SCHEME));
        if ($scheme !== 'https') {
            return new \WP_Error(
                'insecure_api_url',
                __('Licensing API URL must use HTTPS — refusing to send license key over plaintext.', 'bricks-static-pro')
            );
        }

        $fullUrl = add_query_arg(['fluent-cart' => $action], $url);

        $defaults = [
            'item_id'         => $this->config['item_id'] ?? '',
            'current_version' => $this->config['version'],
            'site_url'        => home_url(),
        ];

        $payload = wp_parse_args($data, $defaults);

        $response = wp_remote_post($fullUrl, [
            'timeout' => 15,
            'body'    => $payload,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (200 !== wp_remote_retrieve_response_code($response)) {
            $errorData = wp_remote_retrieve_body($response);
            $message   = 'API request failed with status code: ' . wp_remote_retrieve_response_code($response);

            if (!empty($errorData)) {
                $decodedData = json_decode($errorData, true);
                if ($decodedData) {
                    $errorData = $decodedData;
                }
                if (is_array($errorData) && !empty($errorData['message'])) {
                    $message = (string) $errorData['message'];
                }
            }

            return new \WP_Error('api_error', $message, $errorData);
        }

        $responseData = json_decode(wp_remote_retrieve_body($response), true);

        if ($responseData) {
            return $responseData;
        }

        return new \WP_Error('api_error', 'API request returned an empty or invalid JSON response.');
    }
}
