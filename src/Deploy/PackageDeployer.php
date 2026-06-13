<?php
/**
 * Package (zip) deploy: one upload + server-side extract, instead of one slow
 * FTP transfer per file.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Deploy;

use WPEasy\BricksStatic\Sync\Compressor;
use WPEasy\BricksStatic\Support\Paths;
use WPEasy\BricksStatic\Support\UrlSafety;
use WPEasy\BricksStatic\Transport\TransportInterface;

defined('ABSPATH') || exit;

/**
 * Builds a zip of the changed files, uploads it plus a one-shot helper over the
 * existing transport, then triggers extraction on the destination over HTTPS.
 * Collapses hundreds of high-latency FTP round trips into a single transfer.
 */
final class PackageDeployer {

    /**
     * Seconds the helper script stays valid after upload.
     */
    private const TTL = 600;

    /**
     * Option-name prefix flagging a destination where package deploy failed, so
     * we stop retrying it (cleared when the connection changes or on reset).
     */
    public const OFF_PREFIX = 'bs_pkg_off_';

    /**
     * Whether this server can build a package locally.
     */
    public static function can_build(): bool {
        return class_exists('ZipArchive');
    }

    /**
     * Whether to verify TLS when calling a destination's helper over its public
     * URL (relaxed for local/dev hosts, like the renderer).
     *
     * @param string $url The destination URL.
     */
    public static function sslverify(string $url): bool {
        // Relax ONLY for true local/dev hosts (which can't hold a public CA cert).
        // is_dev_host uses exact matching, so e.g. "127.0.0.1.attacker.com" — a real
        // public host — is verified, not silently exempted.
        $local = UrlSafety::is_dev_host((string) wp_parse_url($url, PHP_URL_HOST));

        /** Filters TLS verification for the package-deploy helper call. */
        return (bool) apply_filters('bs_package_sslverify', !$local, $url);
    }

    /**
     * Public URL a destination is served from (used to call the helper): its
     * configured Destination URL, else a best-effort guess from the host.
     *
     * @param \WPEasy\BricksStatic\Settings\Destination|null $dest Destination.
     */
    public static function base_url($dest): string {
        if ($dest === null) {
            return '';
        }
        $url = trim((string) $dest->get('destinationUrl'));
        if ($url === '') {
            $host = (string) ($dest->connection_config()['host'] ?? '');
            $url  = $host !== '' ? 'https://' . $host : '';
        }

        return $url;
    }

    /**
     * Whether a destination can be deployed as a package: this host can build a
     * zip, the destination has a callable URL, and package deploy hasn't already
     * failed there.
     *
     * @param string                                          $dest_id Destination id.
     * @param \WPEasy\BricksStatic\Settings\Destination|null  $dest    Destination.
     */
    public static function available_for(string $dest_id, $dest): bool {
        return self::can_build()
            && self::base_url($dest) !== ''
            && !get_option(self::OFF_PREFIX . $dest_id);
    }

    /**
     * Deploy a set of files via the package strategy.
     *
     * @param TransportInterface    $transport Connected transport for the destination.
     * @param string                $base_url  Public URL the destination is served from.
     * @param array<string,string>  $files     Map of remote-relative path => local source file.
     * @param array<int,string>     $deletes   Remote-relative paths to remove.
     * @param bool                  $sslverify Whether to verify TLS when calling the helper.
     * @param callable|null         $progress  Optional fn(string $stage) for UI feedback.
     * @return array{ok:bool,extracted:int,deleted:int,errors:array<int,string>,message:string}
     */
    public static function deploy(
        TransportInterface $transport,
        string $base_url,
        array $files,
        array $deletes,
        bool $sslverify,
        ?callable $progress = null
    ): array {
        $report = static function (string $stage) use ($progress): void {
            if ($progress !== null) {
                $progress($stage);
            }
        };
        // 'retryable' = the failure may be transient (network, timeout, FTP), so
        // the caller should fall back for THIS run but try package again next time.
        // Only a definitive "this host can't run the helper" disables package.
        $fail = static fn(string $msg, bool $retryable = true): array => [
            'ok' => false, 'retryable' => $retryable, 'extracted' => 0, 'deleted' => 0, 'errors' => [], 'message' => $msg,
        ];

        if (!self::can_build()) {
            return $fail('ZipArchive is not available on this server.');
        }
        if ($base_url === '') {
            return $fail('A destination URL is required for package deploy.');
        }

        $token    = bin2hex(random_bytes(16));
        $zip_name = 'bs-pkg-' . $token . '.zip';
        $php_name = 'bs-unzip-' . $token . '.php';
        $zip_path = Paths::cache_dir() . '/' . $zip_name;
        $php_path = Paths::cache_dir() . '/' . $php_name;

        // Build the package.
        $report(sprintf('Building deploy package (%d files)…', count($files)));
        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return $fail('Could not create the deploy package.');
        }
        $added = 0;
        foreach ($files as $relative => $source) {
            if (is_string($source) && is_file($source)) {
                $zip->addFile($source, ltrim($relative, '/'));
                $added++;
            }
        }
        $zip->close();

        file_put_contents($php_path, UnzipScript::generate($token, $zip_name, time() + self::TTL, Compressor::extensions(), Compressor::min_bytes()));

        // Upload package + helper, then trigger extraction.
        $report(sprintf('Uploading package (%s)…', size_format((int) @filesize($zip_path) ?: 0)));
        try {
            $transport->put($zip_path, $zip_name);
            $transport->put($php_path, $php_name);
        } catch (\Throwable $e) {
            self::cleanup_local($zip_path, $php_path);
            self::cleanup_remote($transport, $zip_name, $php_name);
            return $fail('Could not upload the deploy package: ' . $e->getMessage());
        }
        self::cleanup_local($zip_path, $php_path);

        $report('Extracting on the destination…');
        // SSRF guard: the destination URL is user-supplied, so validate it resolves
        // to a public (or loopback-for-dev) address and don't follow redirects.
        $response = UrlSafety::guarded_post(rtrim($base_url, '/') . '/' . $php_name, [
            'timeout'   => 120,
            'sslverify' => $sslverify,
            'body'      => ['token' => $token, 'deletes' => wp_json_encode(array_values($deletes))],
        ]);

        // Always remove the helper + package from the remote, even if the call
        // failed or the host doesn't run PHP (so we never leave artifacts behind).
        self::cleanup_remote($transport, $zip_name, $php_name);

        if (is_wp_error($response)) {
            return $fail('Could not reach the deploy helper: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code === 200 && is_array($data) && !empty($data['ok'])) {
            return [
                'ok'        => true,
                'retryable' => true,
                'extracted' => (int) ($data['extracted'] ?? $added),
                'deleted'   => (int) ($data['deleted'] ?? 0),
                'errors'    => is_array($data['errors'] ?? null) ? $data['errors'] : [],
                'message'   => 'Package deployed.',
            ];
        }

        // A 200 that isn't our JSON means the host returned the script as text or
        // an error page — it can't run PHP/ZipArchive here, so disable package
        // (not retryable). A non-200 (timeout, 5xx, wrong URL) might be transient.
        $definitive = ($code === 200);

        return $fail('Deploy helper failed (HTTP ' . $code . ') — the host may not run PHP here.', !$definitive);
    }

    /**
     * Remove local temp artifacts.
     */
    private static function cleanup_local(string ...$paths): void {
        foreach ($paths as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }

    /**
     * Best-effort removal of the package + helper from the destination.
     */
    private static function cleanup_remote(TransportInterface $transport, string ...$names): void {
        foreach ($names as $n) {
            try {
                $transport->delete($n);
            } catch (\Throwable $e) {
                // Already gone (the helper self-deletes on success) — fine.
            }
        }
    }
}
