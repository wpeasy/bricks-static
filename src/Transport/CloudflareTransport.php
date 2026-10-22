<?php
/**
 * Cloudflare (Workers static assets) transport.
 *
 * @package WPEasy\BricksStatic
 * @since   2.1.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Transport;

use WPEasy\BricksStatic\Deploy\CloudflareAuthException;
use WPEasy\BricksStatic\Deploy\CloudflareClient;
use WPEasy\BricksStatic\Deploy\CloudflareHeaders;

defined('ABSPATH') || exit;

/**
 * Publishes the export as an assets-only Cloudflare Worker (`docs/CLOUDFLARE_PLAN.md`).
 *
 * It is a `SnapshotCapableTransport`: every deploy sends the full manifest and
 * Cloudflare asks only for the files it doesn't already hold, then the new set
 * goes live atomically. The per-file `TransportInterface` methods exist only to
 * satisfy the contract (the Runner never routes a snapshot destination through
 * them): `put()` throws, the read/delete methods are inert.
 *
 * "Connected" means the token + account id can read the account's Workers
 * subdomain (`CloudflareClient::account_subdomain()`).
 */
final class CloudflareTransport implements TransportInterface, SnapshotCapableTransport {

    /**
     * Cloudflare's per-file limit for Workers static assets (25 MiB).
     */
    public const MAX_FILE_BYTES = 25 * 1024 * 1024;

    /**
     * Base64 chunk size for streaming hashes — a multiple of 3 so the
     * concatenated chunk encodings equal the encoding of the whole file.
     */
    private const HASH_CHUNK = 3 * 262144;

    private CloudflareClient $client;

    private string $worker;

    private string $destination_url;

    /**
     * The account's workers.dev subdomain, learned on connect() ('' = none).
     */
    private string $subdomain = '';

    private bool $connected = false;

    /**
     * @param array<string,mixed> $config Destination connection config.
     */
    public function __construct(array $config) {
        $this->client          = new CloudflareClient((string) ($config['cfAccountId'] ?? ''), (string) ($config['cfApiToken'] ?? ''));
        $this->worker          = (string) ($config['cfWorker'] ?? '');
        $this->destination_url = (string) ($config['url'] ?? '');
    }

    /**
     * Always usable — needs nothing beyond the WP HTTP API.
     */
    public static function is_available(): bool {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function connect(): void {
        if (!$this->client->configured()) {
            throw new CloudflareAuthException('Enter the Cloudflare account ID and API token for this destination first.');
        }
        if (!CloudflareClient::valid_worker($this->worker)) {
            throw new \RuntimeException('Enter a Worker name (lowercase letters, numbers and dashes) for this destination.');
        }
        $this->subdomain = $this->client->account_subdomain();
        $this->connected = true;
    }

    /**
     * @inheritdoc
     */
    public function disconnect(): void {
        $this->connected = false;
    }

    /**
     * The address Cloudflare serves this Worker at on workers.dev, or '' when
     * the account has no workers.dev subdomain yet. Valid after connect().
     */
    public function workers_dev_url(): string {
        return $this->subdomain !== '' ? sprintf('https://%s.%s.workers.dev', $this->worker, $this->subdomain) : '';
    }

    /**
     * One-line summary for the Test result. Valid after connect().
     */
    public function describe(): string {
        $url = $this->workers_dev_url();

        return $url !== ''
            ? sprintf('Worker "%s" will be served at %s (or your custom domain)', $this->worker, $url)
            : sprintf('Worker "%s" — this account has no workers.dev subdomain yet, so attach a custom domain to the Worker in the Cloudflare dashboard after the first sync', $this->worker);
    }

    /**
     * @inheritdoc
     */
    public function deploy_snapshot(array $files, ?callable $progress = null): array {
        $out = ['ok' => false, 'retryable' => true, 'uploaded' => 0, 'skipped' => [], 'errors' => [], 'message' => ''];
        $say = static function (string $stage) use ($progress): void {
            if ($progress !== null) {
                $progress($stage);
            }
        };

        try {
            if (!$this->connected) {
                $this->connect();
            }

            // 1. Manifest of the full set: '/path' => {hash, size}. Keep a
            //    hash → file index for the upload step.
            $say('Hashing files for Cloudflare…');
            $manifest = [];
            $by_hash  = [];
            $i        = 0;
            foreach ($files as $relative => $path) {
                if (++$i % 200 === 0) {
                    $say(sprintf('Hashing files for Cloudflare… (%d of %d)', $i, count($files)));
                }
                $size = is_file($path) ? (int) filesize($path) : -1;
                if ($size < 0) {
                    $out['skipped'][] = $relative;
                    $out['errors'][]  = $relative . ': staged file missing';
                    continue;
                }
                if ($size > self::MAX_FILE_BYTES) {
                    $out['skipped'][] = $relative;
                    $out['errors'][]  = $relative . ': larger than Cloudflare’s 25 MiB per-file limit';
                    continue;
                }
                $hash = self::hash_file($path, $relative);
                $manifest['/' . ltrim($relative, '/')] = ['hash' => $hash, 'size' => $size];
                $by_hash[$hash] = ['path' => $path, 'mime' => self::mime_for($relative), 'size' => $size];
            }
            if ($manifest === []) {
                $out['retryable'] = false;
                $out['message']   = 'Nothing to publish — no deployable files.';
                return $out;
            }

            // 2. Session + upload whatever Cloudflare is missing. An upload
            //    JWT lasts an hour; if it expires mid-way, start a new session
            //    once — already-uploaded files drop out of its buckets.
            $say('Asking Cloudflare which files it needs…');
            $session    = $this->client->start_session($this->worker, $manifest);
            $completion = $session['buckets'] === [] ? $session['jwt'] : '';
            $restarted  = false;

            for ($b = 0; $b < count($session['buckets']); $b++) {
                $bucket = $session['buckets'][$b];
                $say(sprintf('Uploading to Cloudflare… (batch %d of %d)', $b + 1, count($session['buckets'])));

                $parts = [];
                foreach ($bucket as $hash) {
                    if (isset($by_hash[$hash])) {
                        $parts[$hash] = ['path' => $by_hash[$hash]['path'], 'mime' => $by_hash[$hash]['mime']];
                    }
                }
                if ($parts === []) {
                    continue;
                }

                try {
                    $jwt = $this->client->upload_bucket($session['jwt'], $parts);
                } catch (CloudflareAuthException $e) {
                    if ($restarted) {
                        throw $e;
                    }
                    $restarted = true;
                    $session   = $this->client->start_session($this->worker, $manifest);
                    $completion = $session['buckets'] === [] ? $session['jwt'] : '';
                    $b = -1; // Restart the loop over the new session's buckets.
                    continue;
                }

                $out['uploaded'] += count($parts);
                if ($jwt !== '') {
                    $completion = $jwt;
                }
            }

            if ($completion === '') {
                throw new \RuntimeException('Cloudflare accepted the uploads but did not return a completion token.');
            }

            // 3. Publish — the new set goes live atomically.
            $say('Publishing on Cloudflare…');
            $this->client->publish($this->worker, $completion, self::assets_config($manifest));

            // Serve on workers.dev unless the site lives on a custom domain.
            $warning = '';
            if ($this->wants_workers_dev()) {
                try {
                    $this->client->enable_workers_dev($this->worker);
                } catch (\Throwable $e) {
                    $warning = ' The workers.dev address could not be enabled: ' . $e->getMessage();
                }
            }

            $out['ok']      = true;
            $out['message'] = sprintf('Published %d file(s) to Cloudflare (%d uploaded, the rest unchanged).', count($manifest), $out['uploaded']) . $warning;
        } catch (CloudflareAuthException $e) {
            $out['retryable'] = false;
            $out['message']   = $e->getMessage();
        } catch (\Throwable $e) {
            $out['message'] = $e->getMessage();
        }

        return $out;
    }

    /**
     * Cloudflare's asset key for a file: SHA-256 over (base64 of the bytes +
     * the extension without its dot), first 32 hex chars — the formula in
     * Cloudflare's own direct-upload example. (wrangler uses BLAKE3 over the
     * same input; the key only has to be a stable content address, and PHP
     * has SHA-256 built in.) Streamed so a 25 MiB file isn't held in memory
     * twice.
     *
     * @param string $path     Local file.
     * @param string $relative Export-relative path (for the extension).
     */
    public static function hash_file(string $path, string $relative): string {
        $ctx = hash_init('sha256');
        $fh  = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Could not read a staged file for hashing.');
        }
        try {
            while (!feof($fh)) {
                $chunk = fread($fh, self::HASH_CHUNK);
                if ($chunk === false) {
                    break;
                }
                if ($chunk !== '') {
                    hash_update($ctx, base64_encode($chunk));
                }
            }
        } finally {
            fclose($fh);
        }
        hash_update($ctx, strtolower((string) pathinfo($relative, PATHINFO_EXTENSION)));

        return substr(hash_final($ctx), 0, 32);
    }

    /**
     * `assets.config` for the publish: directory-style URLs (`/about/` →
     * `about/index.html`), the export's 404 page when it has one, and the
     * cache rules from {@see CloudflareHeaders}.
     *
     * @param array<string,array{hash:string,size:int}> $manifest Published manifest.
     * @return array<string,string>
     */
    private static function assets_config(array $manifest): array {
        $config = [
            'html_handling'      => 'auto-trailing-slash',
            'not_found_handling' => isset($manifest['/404.html']) ? '404-page' : 'none',
        ];
        $headers = CloudflareHeaders::headers();
        if (trim($headers) !== '') {
            $config['_headers'] = $headers;
        }
        $redirects = CloudflareHeaders::redirects();
        if (trim($redirects) !== '') {
            $config['_redirects'] = $redirects;
        }

        return $config;
    }

    /**
     * Whether to turn on the workers.dev address: when no Destination URL is
     * set yet, or the Destination URL IS the workers.dev address. A site on
     * its own domain keeps workers.dev off (no duplicate public copy).
     */
    private function wants_workers_dev(): bool {
        $host = strtolower((string) wp_parse_url($this->destination_url, PHP_URL_HOST));

        return $host === '' || str_ends_with($host, '.workers.dev');
    }

    /**
     * MIME type Cloudflare should serve a file with. `application/null` tells
     * Cloudflare to send no Content-Type (for unknown extensions).
     *
     * @param string $relative Export-relative path.
     */
    private static function mime_for(string $relative): string {
        $ext = strtolower((string) pathinfo($relative, PATHINFO_EXTENSION));

        $web = [
            'html'  => 'text/html; charset=utf-8',
            'htm'   => 'text/html; charset=utf-8',
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'mjs'   => 'application/javascript; charset=utf-8',
            'json'  => 'application/json; charset=utf-8',
            'map'   => 'application/json; charset=utf-8',
            'xml'   => 'application/xml; charset=utf-8',
            'txt'   => 'text/plain; charset=utf-8',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'avif'  => 'image/avif',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'eot'   => 'application/vnd.ms-fontobject',
        ];
        if (isset($web[$ext])) {
            return $web[$ext];
        }

        foreach (wp_get_mime_types() as $exts => $mime) {
            if (in_array($ext, explode('|', $exts), true)) {
                return $mime;
            }
        }

        return 'application/null';
    }

    /**
     * Not used: snapshot destinations never upload per file.
     *
     * @inheritdoc
     */
    public function put(string $local_path, string $remote_path): void {
        throw new \RuntimeException('Cloudflare destinations publish whole snapshots; per-file upload is not supported.');
    }

    /**
     * @inheritdoc
     */
    public function mkdir(string $remote_path): void {
        // No directories on Cloudflare — paths are keys.
    }

    /**
     * @inheritdoc
     */
    public function exists(string $remote_path): bool {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function rename(string $from, string $to): bool {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function get(string $remote_path): string {
        return '';
    }

    /**
     * Not used: a file is removed by leaving it out of the next snapshot.
     *
     * @inheritdoc
     */
    public function delete(string $remote_path): bool {
        return false;
    }
}
