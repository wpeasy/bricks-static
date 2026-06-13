<?php
/**
 * Batched sync orchestrator.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\Deploy\PackageDeployer;
use WPEasy\BricksStatic\Discovery\UrlCollector;
use WPEasy\BricksStatic\Settings\Destinations;
use WPEasy\BricksStatic\Render\AssetExtractor;
use WPEasy\BricksStatic\Render\CompatibilityScanner;
use WPEasy\BricksStatic\Render\PageRenderer;
use WPEasy\BricksStatic\Render\StableHash;
use WPEasy\BricksStatic\Render\TextReplacer;
use WPEasy\BricksStatic\Render\UrlRewriter;
use WPEasy\BricksStatic\Support\Paths;
use WPEasy\BricksStatic\Support\Url;
use WPEasy\BricksStatic\Transport\TransportFactory;
use WPEasy\BricksStatic\Transport\TransportInterface;

defined('ABSPATH') || exit;

/**
 * Drives a run as resumable batches: collect → render → assets → finalize.
 * Each tick() does a bounded slice of work and returns a UI snapshot. M2 stops
 * at a local render ("check"); M3 adds the upload phase.
 */
final class Runner {

    /**
     * Pages rendered per tick. Kept small so progress updates frequently and
     * each tick holds a worker only briefly (helps low-worker hosts).
     */
    private const PAGE_BATCH = 2;

    /**
     * Assets copied per tick.
     */
    private const ASSET_BATCH = 10;

    /**
     * Files uploaded per tick (one connection per tick, so keep it generous).
     */
    private const UPLOAD_BATCH = 15;

    /**
     * The destination home page, deferred and swapped in last during a push.
     */
    private const HOME_FILE = 'index.html';

    /**
     * Start a fresh run: prepare the cache, seed the queue, enter render phase.
     *
     * @param string              $type    Run type ('check' or 'sync').
     * @param array<string,mixed> $options Run options (e.g. ['prune' => true]).
     * @return array<string,mixed> Snapshot.
     * @throws \RuntimeException If the cache directory is not writable.
     */
    public static function start(string $type = 'check', array $options = []): array {
        if (!Paths::ensure()) {
            throw new \RuntimeException('The staging cache directory is not writable: ' . Paths::cache_dir());
        }

        self::reset_output();
        Job::clear();
        delete_option(self::CANCEL_FLAG);
        delete_option(self::HEARTBEAT);
        self::release_driver();

        $job = Job::create($type);
        $job->data['prune'] = !empty($options['prune']);

        // Which destination(s) this run pushes to. 'targets' is a list (for
        // "sync all"); 'destId' is the single-target shorthand; default primary.
        $targets = [];
        if (isset($options['targets']) && is_array($options['targets'])) {
            $targets = array_values(array_filter(array_map('strval', $options['targets'])));
        }
        if (empty($targets)) {
            $targets = [(string) ($options['destId'] ?? Destinations::primary()->id())];
        }
        $job->data['targets'] = $targets;
        $job->data['destId']  = $targets[0];
        foreach (UrlCollector::collect() as $url) {
            $job->enqueue_page($url);
        }

        $job->data['phase']            = 'render';
        $job->data['message']          = 'Rendering pages…';
        $job->data['totals']['pages']  = count($job->data['queue']['pages']);
        $job->save();

        return self::snapshot($job);
    }

    /**
     * Re-upload just the files that failed to push, without re-rendering.
     *
     * Reuses the last render: start_target() rebuilds the deploy manifest from it
     * and diffs against the pushed manifest, which still excludes the previously
     * failed files — so the upload queue becomes exactly those files. Targets the
     * same destination(s) as the run that left failures.
     *
     * @return array<string,mixed> Snapshot.
     * @throws \RuntimeException If nothing has been rendered yet.
     */
    public static function start_retry(): array {
        if (!Paths::ensure()) {
            throw new \RuntimeException('The staging cache directory is not writable: ' . Paths::cache_dir());
        }

        if (empty(Manifest::load(Manifest::RENDER_OPTION))) {
            throw new \RuntimeException('Nothing has been rendered yet — run Sync first.');
        }

        $previous = Job::load();
        $targets  = ($previous !== null && !empty($previous->data['targets']))
            ? array_values(array_map('strval', $previous->data['targets']))
            : [Destinations::primary()->id()];

        Job::clear();
        delete_option(self::CANCEL_FLAG);
        delete_option(self::HEARTBEAT);
        self::release_driver();

        $job = Job::create('sync');
        $job->data['prune']       = false; // A retry only re-pushes, never deletes.
        $job->data['targets']     = $targets;
        $job->data['targetIndex'] = 0;
        self::start_target($job); // → phase 'upload', queue = the still-missing files
        $job->save();

        return self::snapshot($job);
    }

    /**
     * Open transport for the current target, cached for the lifetime of the
     * process so consecutive upload/prune ticks reuse ONE connection instead of
     * re-handshaking every batch (a real saving for FTPS, where each connect is a
     * TLS handshake + login). The CLI runner drives the whole job in one process,
     * so it keeps the connection open across all batches; a browser tick is its
     * own process, so it simply reconnects per request as before.
     */
    private static ?TransportInterface $connection = null;

    /**
     * Destination id the cached connection belongs to.
     */
    private static string $connection_dest = '';

    /**
     * Whether the process-end close handler has been registered.
     */
    private static bool $shutdown_registered = false;

    /**
     * Get (or open) the persistent transport for the job's current target.
     * Reconnects when the target changes or no connection is open yet.
     *
     * @param Job $job Active job.
     * @throws \Throwable On connection failure.
     */
    private static function target_transport(Job $job): TransportInterface {
        $dest_id = (string) ($job->data['destId'] ?? '');

        if (self::$connection !== null && self::$connection_dest === $dest_id) {
            return self::$connection;
        }

        self::close_connection(); // Different target (or stale) — drop it.

        $dest      = Destinations::get($dest_id);
        $transport = TransportFactory::make($dest !== null ? $dest->connection_config() : null);
        $transport->connect();

        self::$connection      = $transport;
        self::$connection_dest = $dest_id;

        // Close the socket cleanly when the process ends (covers a browser tick's
        // single request as well as the CLI runner finishing the job).
        if (!self::$shutdown_registered) {
            register_shutdown_function([self::class, 'close_connection']);
            self::$shutdown_registered = true;
        }

        return $transport;
    }

    /**
     * Close and forget the persistent transport, if any.
     */
    public static function close_connection(): void {
        if (self::$connection !== null) {
            try {
                self::$connection->disconnect();
            } catch (\Throwable $e) {
                // Already gone — nothing to do.
            }
            self::$connection      = null;
            self::$connection_dest = '';
        }
    }

    /**
     * Process one batch of the active job.
     *
     * @return array<string,mixed> Snapshot.
     */
    public static function tick(): array {
        $job = Job::load();
        if ($job === null) {
            return ['phase' => 'idle'];
        }

        self::beat(); // Mark the job as actively driven (see is_driver_alive()).

        if (self::is_cancelled()) {
            self::close_connection();
            // If we'd already swapped in the holding page, put the real home back.
            if (!empty($job->data['holdingShown'])) {
                self::restore_home();
            }
            delete_option(self::CANCEL_FLAG);
            $job->data['phase']   = 'cancelled';
            $job->data['message'] = 'Cancelled.';
            $job->save();
            return self::snapshot($job);
        }

        switch ($job->data['phase']) {
            case 'render':
                self::tick_render($job);
                break;
            case 'assets':
                self::tick_assets($job);
                break;
            case 'finalize':
                self::tick_finalize($job);
                break;
            case 'package':
                self::tick_package($job);
                break;
            case 'upload':
                self::tick_upload($job);
                break;
            case 'prune':
                self::tick_prune($job);
                break;
        }

        return self::snapshot($job);
    }

    /**
     * Option holding the cancel flag, kept separate from the job blob so a
     * concurrent tick's save() cannot clobber it.
     */
    private const CANCEL_FLAG = 'bs_cancel';

    /**
     * Option holding the last "a driver is actively working" timestamp.
     */
    private const HEARTBEAT = 'bs_driver_heartbeat';

    /**
     * Seconds since the last heartbeat after which the driver is presumed dead.
     * Generous so a single slow page render doesn't look like a stalled process.
     */
    private const HEARTBEAT_TTL = 25;

    /**
     * Stamp the heartbeat — called as work progresses so the dashboard can tell
     * whether SOMETHING (a spawned WP-CLI process, or its own ticks) is driving
     * the job. Lets the browser take over only when a spawn genuinely failed to
     * launch, without racing a slow-but-alive CLI process.
     */
    private static function beat(): void {
        update_option(self::HEARTBEAT, time(), false);
    }

    /**
     * Option naming the single process allowed to drive the job ('cli' | 'browser').
     */
    private const DRIVER_OPTION = 'bs_driver';

    /**
     * Atomically claim the right to drive the active job. Only ONE driver may
     * advance a job at a time: on Local's tiny php-fpm pool, a browser tick AND a
     * spawned WP-CLI process both running loopback renders starves the workers and
     * deadlocks. The first caller to insert the row wins (option_name is unique);
     * everyone else reads back the existing owner and must stand down.
     *
     * @param string $who 'cli' or 'browser'.
     * @return string The winning owner (=== $who if this caller claimed it).
     */
    public static function claim_driver(string $who): string {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::DRIVER_OPTION,
            $who
        ));

        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            self::DRIVER_OPTION
        ));
    }

    /**
     * Release the driver claim (direct delete so a long-lived process can't serve
     * a stale cached value to the next run).
     */
    private static function release_driver(): void {
        global $wpdb;
        $wpdb->delete($wpdb->options, ['option_name' => self::DRIVER_OPTION]);
    }

    /**
     * Whether the job has been touched by a driver within the heartbeat TTL.
     * Read straight from the DB (cross-process, cache-bypassing) like the cancel
     * flag, so a polling status request sees the CLI process's latest beat.
     */
    private static function is_driver_alive(): bool {
        global $wpdb;
        $ts = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::HEARTBEAT)
        );
        return $ts > 0 && (time() - $ts) <= self::HEARTBEAT_TTL;
    }

    /**
     * Request cancellation of the active job.
     */
    public static function cancel(): void {
        update_option(self::CANCEL_FLAG, '1', false);
    }

    /**
     * Whether cancellation has been requested.
     *
     * Read straight from the DB, NOT via get_option(): the WP-CLI background
     * runner drives the whole job inside one long-lived process, which caches
     * options in memory. get_option() would keep returning the value cached when
     * the job started and never see the flag the (separate) cancel request set.
     */
    private static function is_cancelled(): bool {
        global $wpdb;
        $val = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::CANCEL_FLAG)
        );
        return $val === '1';
    }

    /**
     * Seconds without progress after which a "running" job is treated as
     * abandoned (the driving tab was closed, or the process died).
     */
    private const STALE_SECONDS = 90;

    /**
     * Current snapshot of the active job without advancing it.
     *
     * A running job that hasn't progressed in a while is auto-discarded so it
     * can never silently resume and hammer the server with loopback renders.
     *
     * @return array<string,mixed>
     */
    public static function status(): array {
        $job = Job::load();
        if ($job === null) {
            return ['phase' => 'idle'];
        }

        $running = !in_array($job->data['phase'], ['done', 'error', 'cancelled'], true);
        if ($running && (time() - (int) $job->data['updatedAt']) > self::STALE_SECONDS) {
            Job::clear();
            delete_option(self::CANCEL_FLAG);
            return ['phase' => 'idle'];
        }

        return self::snapshot($job);
    }

    /**
     * Render a batch of queued pages.
     *
     * @param Job $job Active job.
     */
    private static function tick_render(Job $job): void {
        $batch = array_splice($job->data['queue']['pages'], 0, self::PAGE_BATCH);

        foreach ($batch as $url) {
            if (self::is_cancelled()) {
                break; // tick() finalizes the cancelled state on the next call.
            }
            self::beat(); // Keep the heartbeat fresh through a slow batch.

            try {
                $result = PageRenderer::render($url);

                if ($result['code'] !== 200) {
                    $job->skip($url, 'HTTP ' . $result['code']);
                    continue;
                }

                if (stripos($result['contentType'], 'html') === false) {
                    // Not a page — treat as an asset to copy verbatim.
                    $job->enqueue_asset($url);
                    continue;
                }

                $relative = Url::to_relative_path($url);
                if ($relative === null) {
                    $job->skip($url, 'unmappable');
                    continue;
                }

                self::cache_file($job, $relative, UrlRewriter::rewrite($result['body']));
                $job->data['counts']['pagesDone']++;

                $compat = CompatibilityScanner::scan($result['body']);
                if (!empty($compat) && count($job->data['compat']) < 200) {
                    $job->data['compat'][] = ['url' => $url, 'issues' => $compat];
                }

                foreach (AssetExtractor::extract_links($result['body'], $url) as $link) {
                    $job->enqueue_page($link);
                }
                foreach (AssetExtractor::extract_assets($result['body'], $url) as $asset) {
                    $job->enqueue_asset($asset);
                }
            } catch (\Throwable $e) {
                $job->error($url, $e->getMessage());
            }
        }

        $job->data['totals']['pages'] = $job->data['counts']['pagesDone'] + count($job->data['queue']['pages']);

        if (empty($job->data['queue']['pages'])) {
            $job->data['phase']           = 'assets';
            $job->data['message']         = $job->data['type'] === 'sync' ? 'Copying assets…' : 'Cataloguing assets…';
            $job->data['totals']['assets'] = count($job->data['queue']['assets']);
        }

        $job->save();
    }

    /**
     * Copy a batch of queued assets.
     *
     * @param Job $job Active job.
     */
    private static function tick_assets(Job $job): void {
        $batch = array_splice($job->data['queue']['assets'], 0, self::ASSET_BATCH);

        foreach ($batch as $url) {
            if (self::is_cancelled()) {
                break;
            }
            self::beat();

            try {
                $relative = Url::to_relative_path($url);
                if ($relative === null) {
                    $job->skip($url, 'unmappable');
                    continue;
                }

                $source = Paths::source_file($url);

                // CSS is read and rewritten (origins → root-relative) and scanned
                // for nested url()/@import background assets, then cached.
                if (self::is_css_url($relative)) {
                    $css = self::read_or_fetch_css($job, $url, $source);
                    if ($css === null) {
                        continue;
                    }
                    foreach (AssetExtractor::extract_css_assets($css, $url) as $nested) {
                        $job->enqueue_asset($nested);
                    }
                    self::cache_file($job, $relative, UrlRewriter::rewrite($css));
                    $job->data['counts']['assetsDone']++;
                    continue;
                }

                // Static asset present on disk: list its source path, never copy.
                if ($source !== null) {
                    self::plan_source($job, $relative, $source);
                    $job->data['counts']['assetsDone']++;
                    continue;
                }

                // No source file (dynamic asset): fetch and cache it.
                $result = PageRenderer::fetch_asset($url);
                if ($result['code'] !== 200) {
                    $job->skip($url, 'HTTP ' . $result['code']);
                    continue;
                }
                // A page that slipped into the asset queue must not be written.
                if (stripos($result['contentType'], 'html') !== false) {
                    continue;
                }
                self::cache_file($job, $relative, $result['body']);
                $job->data['counts']['assetsDone']++;
            } catch (\Throwable $e) {
                $job->error($url, $e->getMessage());
            }
        }

        $job->data['totals']['assets'] = $job->data['counts']['assetsDone'] + count($job->data['queue']['assets']);

        if (empty($job->data['queue']['assets'])) {
            $job->data['phase']   = 'finalize';
            $job->data['message'] = 'Finalising…';
        }

        $job->save();
    }

    /**
     * Build the render manifest and complete the job.
     *
     * @param Job $job Active job.
     */
    private static function tick_finalize(Job $job): void {
        $manifest = Manifest::from_plan($job->data['plan']);
        Manifest::save(Manifest::RENDER_OPTION, $manifest);
        self::write_manifest_file($job, $manifest);

        $job->data['counts']['files'] = count($manifest);

        if ($job->data['type'] !== 'sync') {
            $job->data['phase']   = 'done';
            $job->data['message'] = 'Done.';
            $job->save();
            return;
        }

        // Sync: deploy the shared render to each target destination in turn.
        if (empty($job->data['targets'])) {
            $job->data['targets'] = [Destinations::primary()->id()];
        }
        $job->data['targetIndex'] = 0;
        self::start_target($job);
        $job->save();
    }

    /**
     * Begin deploying to the current target: build its deploy manifest, compute
     * the delta, and reset the per-target upload state.
     *
     * @param Job $job Active job.
     */
    private static function start_target(Job $job): void {
        $dest_id = (string) ($job->data['targets'][$job->data['targetIndex']] ?? '');
        $job->data['destId'] = $dest_id;

        $dest = Destinations::get($dest_id);
        $name = $dest !== null ? (string) $dest->get('name') : $dest_id;

        $deploy = self::build_deploy_manifest(Manifest::load(Manifest::RENDER_OPTION), $dest_id);
        Manifest::save(Manifest::DEPLOY_OPTION, $deploy);

        $diff = Manifest::diff(Manifest::load(self::pushed_option($job)), $deploy);

        $job->data['queue']['uploads']   = $diff['changed'];
        $job->data['removedFiles']       = $diff['removed'];
        $job->data['removed']            = count($diff['removed']);
        $job->data['totals']['uploads']  = count($diff['changed']);
        $job->data['counts']['uploaded'] = 0;
        $job->data['counts']['pruned']   = 0;
        $job->data['failed']             = [];
        $job->data['htaccessDone']       = false;
        $job->data['holdingShown']       = false;

        // Prefer the package strategy (one zip + server-side extract) when the
        // host can run it; otherwise fall back to per-file uploads.
        if (self::should_package($dest_id)) {
            $job->data['phase']   = 'package';
            $job->data['message'] = sprintf('Packaging deploy for %s…', $name);
        } else {
            $job->data['phase']   = 'upload';
            $job->data['message'] = sprintf('Uploading to %s…', $name);
        }
    }

    /**
     * Whether to deploy to a destination as a single package (see
     * PackageDeployer::available_for).
     */
    private static function should_package(string $dest_id): bool {
        return PackageDeployer::available_for($dest_id, Destinations::get($dest_id));
    }

    /**
     * Advance to the next target destination, or finish.
     *
     * @param Job $job Active job.
     */
    private static function advance_target(Job $job): void {
        $job->data['targetsDone']++;
        $job->data['targetIndex']++;

        if ($job->data['targetIndex'] < count($job->data['targets'])) {
            self::start_target($job);
            return;
        }

        $job->data['phase']   = 'done';
        $job->data['message'] = self::done_message($job);
    }

    /**
     * Build the deploy manifest for a destination: the base render with that
     * destination's literal text replacements applied to HTML (written to a
     * per-destination deploy dir). Files without changes reference the base.
     *
     * @param array<string,array{size:int,hash:string,src:string}> $base    Base render manifest.
     * @param string                                                $dest_id Target destination id.
     * @return array<string,array{size:int,hash:string,src:string}>
     */
    private static function build_deploy_manifest(array $base, string $dest_id): array {
        $dest         = $dest_id !== '' ? Destinations::get($dest_id) : Destinations::primary();
        $replacements = $dest !== null ? $dest->replacements() : [];

        if (empty($replacements)) {
            return $base; // No transform — deploy the base render verbatim.
        }

        $searches = array_column($replacements, 'search');
        $replaces = array_column($replacements, 'replace');

        $deploy_dir = Paths::cache_dir() . '/deploy/' . ($dest !== null ? $dest->id() : 'default');
        self::reset_dir($deploy_dir);

        $out = [];
        foreach ($base as $relative => $meta) {
            if (substr(strtolower($relative), -5) !== '.html' || !is_file($meta['src'])) {
                $out[$relative] = $meta;
                continue;
            }

            $transformed = TextReplacer::apply((string) file_get_contents($meta['src']), $searches, $replaces);
            $path        = $deploy_dir . '/' . $relative;
            $dir         = dirname($path);

            if ((!is_dir($dir) && !wp_mkdir_p($dir)) || file_put_contents($path, $transformed) === false) {
                $out[$relative] = $meta; // fall back to base on write failure
                continue;
            }
            if (Compressor::is_compressible($relative)) {
                Compressor::write_sibling($path);
            }

            $out[$relative] = ['size' => strlen($transformed), 'hash' => StableHash::of_html($transformed), 'src' => wp_normalize_path($path)];
        }

        return $out;
    }

    /**
     * Recursively empty (and create) a directory.
     *
     * @param string $dir Directory.
     */
    private static function reset_dir(string $dir): void {
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }

    /**
     * Drop a human-readable manifest.json in the cache root (a convenience copy
     * of the DB manifest; the database remains the source of truth). It lives
     * beside the staging guards, outside the uploaded site/ tree.
     *
     * @param Job                                                    $job      Active job.
     * @param array<string,array{size:int,hash:string,src:string}>   $manifest Manifest.
     */
    private static function write_manifest_file(Job $job, array $manifest): void {
        $cache_root = wp_normalize_path(Paths::output_dir());
        $entries    = [];

        foreach ($manifest as $relative => $meta) {
            $entries[$relative] = [
                'size'   => $meta['size'],
                'hash'   => $meta['hash'],
                'origin' => strpos($meta['src'], $cache_root) === 0 ? 'cache' : 'source',
                'src'    => $meta['src'],
            ];
        }

        $document = [
            'generatedAt' => time(),
            'type'        => $job->data['type'],
            'files'       => count($manifest),
            'bytes'       => $job->data['counts']['bytes'],
            'entries'     => $entries,
        ];

        $json = wp_json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            file_put_contents(Paths::cache_dir() . '/manifest.json', $json);
        }
    }

    /**
     * Deploy the whole target as a single package: upload one zip + a one-shot
     * helper, then extract server-side. Falls back to per-file uploads if the
     * host can't run the helper.
     *
     * @param Job $job Active job.
     */
    private static function tick_package(Job $job): void {
        try {
            $transport = self::target_transport($job);
        } catch (\Throwable $e) {
            self::close_connection();
            $job->data['phase']   = 'error';
            $job->data['message'] = 'Upload connection failed: ' . $e->getMessage();
            $job->save();
            return;
        }

        $manifest = Manifest::load(Manifest::DEPLOY_OPTION);

        self::beat();

        // Server config first (merge-safe), same as the per-file path.
        self::upload_htaccess($transport);
        update_option('bs_nginx_snippet', HtaccessBuilder::nginx(), false);
        self::beat();

        $files = [];
        foreach ($job->data['queue']['uploads'] as $relative) {
            $meta = $manifest[$relative] ?? null;
            if ($meta !== null && is_file($meta['src'])) {
                $files[$relative] = $meta['src'];
            }
        }

        $deletes  = !empty($job->data['prune']) ? array_values($job->data['removedFiles']) : [];
        $dest     = Destinations::get((string) ($job->data['destId'] ?? ''));
        $base_url = PackageDeployer::base_url($dest);

        // Persist each stage so the dashboard's status poll shows live progress
        // through the single package operation.
        $progress = function (string $stage) use ($job): void {
            $job->data['message'] = $stage;
            self::beat();
            $job->save();
        };

        $result = PackageDeployer::deploy($transport, $base_url, $files, $deletes, PackageDeployer::sslverify($base_url), $progress);

        if (empty($result['ok'])) {
            // Only permanently disable package deploy on a DEFINITIVE failure
            // (host can't run the helper). A transient failure (network, timeout,
            // FTP) just falls back for this run and retries package next time.
            if (empty($result['retryable'])) {
                update_option(PackageDeployer::OFF_PREFIX . (string) ($job->data['destId'] ?? ''), 1, false);
            }
            $job->data['phase']        = 'upload';
            $job->data['htaccessDone'] = false;
            $job->data['holdingShown'] = false;
            $job->data['message']      = 'Package deploy unavailable here — uploading file by file…';
            $job->save();
            return;
        }

        // The remote now holds the full deploy manifest.
        Manifest::save(self::pushed_option($job), Manifest::pushed_from($manifest));
        $job->data['counts']['uploaded'] += (int) $result['extracted'];
        $job->data['counts']['pruned']   += (int) $result['deleted'];
        $job->data['queue']['uploads']    = [];
        $job->data['removedFiles']        = [];

        self::advance_target($job);
        if (in_array($job->data['phase'], ['done', 'error', 'cancelled'], true)) {
            self::close_connection();
        }
        $job->save();
    }

    /**
     * Upload a batch of changed files (plus a one-time .htaccess) to the
     * destination, over a connection kept open across batches (see
     * target_transport()).
     *
     * @param Job $job Active job.
     */
    private static function tick_upload(Job $job): void {
        $manifest = Manifest::load(Manifest::DEPLOY_OPTION);

        try {
            $transport = self::target_transport($job);
        } catch (\Throwable $e) {
            self::close_connection();
            $job->data['phase']   = 'error';
            $job->data['message'] = 'Upload connection failed: ' . $e->getMessage();
            $job->save();
            return;
        }

        try {
            // One-time setup: defer the home page, show a holding page while the
            // rest uploads, then write server config + store the nginx snippet.
            if (empty($job->data['htaccessDone'])) {
                // Home is uploaded last (after any holding page). Note whether it
                // actually changed so we can skip re-uploading an unchanged one.
                $job->data['homeChanged'] = in_array(self::HOME_FILE, $job->data['queue']['uploads'], true);

                $job->data['queue']['uploads'] = array_values(array_filter(
                    $job->data['queue']['uploads'],
                    static fn(string $rel): bool => $rel !== self::HOME_FILE
                ));

                if (!empty($job->data['queue']['uploads'])) {
                    self::upload_holding_page($transport);
                    $job->data['holdingShown'] = true;
                }

                self::upload_htaccess($transport);
                update_option('bs_nginx_snippet', HtaccessBuilder::nginx(), false);
                $job->data['htaccessDone'] = true;
                // +1 for the home page only when we'll actually (re)upload it:
                // because it changed, or to replace a holding page we put up.
                $will_upload_home = !empty($job->data['homeChanged']) || !empty($job->data['holdingShown']);
                $job->data['totals']['uploads'] = count($job->data['queue']['uploads']) + ($will_upload_home ? 1 : 0);
            }

            $batch = array_splice($job->data['queue']['uploads'], 0, self::UPLOAD_BATCH);
            foreach ($batch as $relative) {
                if (self::is_cancelled()) {
                    break;
                }
                self::beat();

                $meta = $manifest[$relative] ?? null;
                if ($meta === null || !is_file($meta['src'])) {
                    $job->skip($relative, 'missing source');
                    continue;
                }

                try {
                    self::put_retry($transport, $meta['src'], $relative);
                    self::upload_gz($transport, $relative, $meta['src']);
                    $job->data['counts']['uploaded']++;
                } catch (\Throwable $e) {
                    $job->error($relative, $e->getMessage());
                    $job->data['failed'][] = $relative;
                }

                // Persist after each file so the dashboard's status poll shows
                // upload progress smoothly, not one jump per batch.
                $job->save();
            }

            // Everything else is up: swap the holding page for the real home,
            // then record the push as complete.
            if (empty($job->data['queue']['uploads']) && !self::is_cancelled()) {
                // (Re)upload the real home only if it changed or we replaced it
                // with a holding page — otherwise it's already correct on the
                // remote and re-uploading it every sync is wasted work.
                if (!empty($job->data['homeChanged']) || !empty($job->data['holdingShown'])) {
                    self::upload_home($transport, $manifest);
                    $job->data['counts']['uploaded']++;
                }

                // Mark only successfully-uploaded files as pushed, so any that
                // failed are re-uploaded on the next sync (delta detects them).
                $pushed = Manifest::pushed_from($manifest);
                foreach ($job->data['failed'] as $failed_rel) {
                    unset($pushed[$failed_rel]);
                }
                Manifest::save(self::pushed_option($job), $pushed);

                // Prune deleted files next, if requested and there are any;
                // otherwise move on to the next destination (or finish).
                if (!empty($job->data['prune']) && !empty($job->data['removedFiles'])) {
                    $job->data['phase']   = 'prune';
                    $job->data['message'] = 'Removing deleted files…';
                } else {
                    self::advance_target($job);
                }
            }
        } finally {
            // Keep the connection open across upload batches (and into the prune
            // phase, same destination); close only once the run has finished.
            // The process-end shutdown handler is the backstop for a browser
            // tick, whose single request ends after this one batch.
            if (in_array($job->data['phase'], ['done', 'error', 'cancelled'], true)) {
                self::close_connection();
            }
        }

        $job->save();
    }

    /**
     * Delete a batch of files that no longer exist locally from the destination.
     *
     * @param Job $job Active job.
     */
    private static function tick_prune(Job $job): void {
        try {
            $transport = self::target_transport($job);
        } catch (\Throwable $e) {
            // Uploads already succeeded; leave leftovers for the next run.
            self::close_connection();
            $job->data['phase']   = 'done';
            $job->data['message'] = 'Pushed. Could not connect to remove old files: ' . $e->getMessage();
            $job->save();
            return;
        }

        $batch = array_splice($job->data['removedFiles'], 0, self::UPLOAD_BATCH);
        foreach ($batch as $relative) {
            if (self::is_cancelled()) {
                break;
            }
            self::beat();

            $transport->delete($relative);
            $transport->delete($relative . '.gz');
            $job->data['counts']['pruned']++;
        }

        if (empty($job->data['removedFiles'])) {
            self::advance_target($job);
        }

        // Reuses the same connection the upload phase opened; close it once the
        // run is finished (shutdown handler is the backstop for a browser tick).
        if (in_array($job->data['phase'], ['done', 'error', 'cancelled'], true)) {
            self::close_connection();
        }

        $job->save();
    }

    /**
     * Name of the destination currently being deployed (for the snapshot).
     *
     * @param array<string,mixed> $data Job data.
     */
    private static function target_name(array $data): string {
        if (empty($data['targets'])) {
            return '';
        }
        $dest = Destinations::get((string) ($data['destId'] ?? ''));

        return $dest !== null ? (string) $dest->get('name') : (string) ($data['destId'] ?? '');
    }

    /**
     * The pushed-manifest option for the job's target destination.
     *
     * @param Job $job Active job.
     */
    private static function pushed_option(Job $job): string {
        $id = (string) ($job->data['destId'] ?? '');
        if ($id === '') {
            $id = Destinations::primary()->id();
        }

        return Destinations::pushed_option($id);
    }

    /**
     * Final status message reflecting failures and pruning.
     *
     * @param Job $job Active job.
     */
    private static function done_message(Job $job): string {
        if (!empty($job->data['failed'])) {
            return 'Pushed with ' . count($job->data['failed']) . ' failed file(s); re-run Sync to retry them.';
        }

        $done = (int) $job->data['targetsDone'];
        if ($done > 1) {
            return sprintf('Pushed to %d destinations — all in sync.', $done);
        }

        $pruned = (int) $job->data['counts']['pruned'];

        return $pruned > 0
            ? 'Pushed — destination is in sync (' . $pruned . ' old file(s) removed).'
            : 'Pushed — destination is in sync.';
    }

    /**
     * Upload a file with a few retries, to ride out transient transport hiccups
     * (FTPS data-channel resets are common).
     *
     * @param TransportInterface $transport Connected transport.
     * @param string             $local     Local file path.
     * @param string             $remote    Remote relative path.
     * @throws \Throwable The last error if all attempts fail.
     */
    private static function put_retry(TransportInterface $transport, string $local, string $remote): void {
        $last = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $transport->put($local, $remote);
                return;
            } catch (\Throwable $e) {
                $last = $e;
            }
        }

        throw $last ?? new \RuntimeException('Upload failed: ' . $remote);
    }

    /**
     * Upload the "Currently Synchronising" holding page to the destination home.
     *
     * @param TransportInterface $transport Connected transport.
     */
    private static function upload_holding_page(TransportInterface $transport): void {
        $tmp = Paths::cache_dir() . '/.holding.out';
        file_put_contents($tmp, self::holding_html());
        self::put_retry($transport, $tmp, self::HOME_FILE);
        @unlink($tmp);
    }

    /**
     * Upload the real home page (and its gzip sibling) from the render manifest.
     *
     * @param TransportInterface                                          $transport Connected transport.
     * @param array<string,array{size:int,hash:string,src:string}>        $manifest  Render manifest.
     */
    private static function upload_home(TransportInterface $transport, array $manifest): void {
        $meta = $manifest[self::HOME_FILE] ?? null;
        if ($meta === null || !is_file($meta['src'])) {
            return;
        }

        self::put_retry($transport, $meta['src'], self::HOME_FILE);
        self::upload_gz($transport, self::HOME_FILE, $meta['src']);
    }

    /**
     * Best-effort restore of the real home page (used when a push is cancelled
     * after the holding page has gone up).
     */
    private static function restore_home(): void {
        try {
            $transport = TransportFactory::make();
            $transport->connect();
            try {
                self::upload_home($transport, Manifest::load(Manifest::RENDER_OPTION));
            } finally {
                $transport->disconnect();
            }
        } catch (\Throwable $e) {
            // Best effort — the next successful sync will restore it.
        }
    }

    /**
     * Minimal, self-contained holding page shown at the destination home during
     * a push. Auto-refreshes so visitors land on the real site once it's live.
     */
    private static function holding_html(): string {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="10">
<meta name="robots" content="noindex">
<title>Updating…</title>
<style>
  :root { color-scheme: light dark; }
  body { margin:0; min-height:100vh; display:grid; place-items:center; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; background:#0f172a; color:#f1f5f9; }
  .box { text-align:center; padding:2rem; }
  .spin { width:42px; height:42px; margin:0 auto 1.25rem; border:4px solid rgba(255,255,255,.2); border-top-color:#3b82f6; border-radius:50%; animation:s 1s linear infinite; }
  h1 { font-size:1.4rem; font-weight:600; margin:0 0 .5rem; }
  p { margin:0; color:#94a3b8; }
  @keyframes s { to { transform:rotate(360deg); } }
</style>
</head>
<body>
  <div class="box">
    <div class="spin"></div>
    <h1>Currently synchronising</h1>
    <p>This site is being updated and will be back shortly.</p>
  </div>
</body>
</html>
HTML;
    }

    /**
     * Write our .htaccess to the destination root, backing up any existing one.
     *
     * @param TransportInterface $transport Connected transport.
     */
    private static function upload_htaccess(TransportInterface $transport): void {
        $existing = $transport->exists('.htaccess') ? $transport->get('.htaccess') : '';

        // One-time backup of the original before we ever merge into it.
        if ($existing !== '' && !$transport->exists('.htaccess.bricks-static.bak')) {
            $bak = Paths::cache_dir() . '/.htaccess.bak.out';
            file_put_contents($bak, $existing);
            self::put_retry($transport, $bak, '.htaccess.bricks-static.bak');
            @unlink($bak);
        }

        $tmp = Paths::cache_dir() . '/.htaccess.out';
        file_put_contents($tmp, HtaccessBuilder::merge($existing));
        self::put_retry($transport, $tmp, '.htaccess');
        @unlink($tmp);
    }

    /**
     * Upload a gzip sibling for a compressible file (cached .gz if present, else
     * generated on the fly from the source).
     *
     * @param TransportInterface $transport Connected transport.
     * @param string             $relative  Destination relative path.
     * @param string             $source    Local source file.
     */
    private static function upload_gz(TransportInterface $transport, string $relative, string $source): void {
        if (!Compressor::is_compressible($relative)) {
            return;
        }

        $sibling = $source . '.gz';
        if (is_file($sibling)) {
            self::put_retry($transport, $sibling, $relative . '.gz');
            return;
        }

        $tmp = Paths::cache_dir() . '/.tmp-upload.gz';
        if (Compressor::gzip_to($source, $tmp)) {
            self::put_retry($transport, $tmp, $relative . '.gz');
            @unlink($tmp);
        }
    }

    /**
     * Write generated content (a page or rewritten CSS) into the cache, gzip it,
     * and record it in the upload plan.
     *
     * @param Job    $job      Active job.
     * @param string $relative Relative file path.
     * @param string $content  File contents.
     */
    private static function cache_file(Job $job, string $relative, string $content): void {
        $path = Paths::output_dir() . '/' . $relative;
        $dir  = dirname($path);

        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return;
        }

        $bytes = file_put_contents($path, $content);
        if ($bytes === false) {
            return;
        }

        if (Compressor::is_compressible($relative)) {
            Compressor::write_sibling($path);
        }

        $job->data['plan'][$relative]  = wp_normalize_path($path);
        $job->data['counts']['bytes'] += (int) $bytes;
    }

    /**
     * Record a static asset to be uploaded straight from its source file —
     * without copying it into the cache.
     *
     * @param Job    $job      Active job.
     * @param string $relative Relative destination path.
     * @param string $source   Absolute source file path.
     */
    private static function plan_source(Job $job, string $relative, string $source): void {
        $job->data['plan'][$relative]  = wp_normalize_path($source);
        $job->data['counts']['bytes'] += (int) @filesize($source);
    }

    /**
     * Read CSS from its source file, or fetch it over HTTP if there is none.
     *
     * @param Job         $job    Active job.
     * @param string      $url    CSS URL.
     * @param string|null $source Source file path, if resolved.
     * @return string|null CSS contents, or null to skip.
     */
    private static function read_or_fetch_css(Job $job, string $url, ?string $source): ?string {
        if ($source !== null) {
            $css = file_get_contents($source);
            return $css === false ? null : $css;
        }

        $result = PageRenderer::fetch_asset($url);
        if ($result['code'] !== 200) {
            $job->skip($url, 'HTTP ' . $result['code']);
            return null;
        }

        return $result['body'];
    }

    /**
     * Whether a relative path is a CSS file (by extension).
     *
     * @param string $relative Relative path.
     */
    private static function is_css_url(string $relative): bool {
        return strtolower(pathinfo($relative, PATHINFO_EXTENSION)) === 'css';
    }

    /**
     * Recursively empty the output directory before a fresh run.
     */
    private static function reset_output(): void {
        $dir = Paths::output_dir();
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
    }

    /**
     * Build a UI-facing snapshot of the job.
     *
     * @param Job $job Active job.
     * @return array<string,mixed>
     */
    private static function snapshot(Job $job): array {
        $d = $job->data;

        return [
            'phase'        => $d['phase'],
            'type'         => $d['type'],
            'message'      => $d['message'],
            'counts'       => $d['counts'],
            'totals'       => $d['totals'],
            'queued'       => [
                'pages'   => count($d['queue']['pages']),
                'assets'  => count($d['queue']['assets']),
                'uploads' => count($d['queue']['uploads'] ?? []),
                'prune'   => count($d['removedFiles'] ?? []),
            ],
            'removed'      => $d['removed'] ?? 0,
            'prune'        => !empty($d['prune']),
            'targets'      => [
                'index' => (int) ($d['targetIndex'] ?? 0),
                'total' => count($d['targets'] ?? []),
                'done'  => (int) ($d['targetsDone'] ?? 0),
                'name'  => self::target_name($d),
            ],
            'errorCount'   => count($d['errors']),
            'skippedCount' => count($d['skipped']),
            'failedCount'  => count($d['failed'] ?? []),
            'compatCount'  => count($d['compat'] ?? []),
            'errors'       => array_slice($d['errors'], -25),
            'skipped'      => array_slice($d['skipped'], -25),
            'compat'       => array_slice($d['compat'] ?? [], 0, 25),
            'startedAt'    => $d['startedAt'],
            'updatedAt'    => $d['updatedAt'],
            'running'      => !in_array($d['phase'], ['done', 'error', 'cancelled', 'idle'], true),
            'cliAlive'     => self::is_driver_alive(),
        ];
    }
}
