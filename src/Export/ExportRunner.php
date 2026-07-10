<?php
/**
 * Export ZIP state machine — packages the current render into a downloadable
 * zip for one destination, without uploading anywhere.
 *
 * @package WPEasy\BricksStatic
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Export;

use WPEasy\BricksStatic\Settings\Destinations;
use WPEasy\BricksStatic\Sync\Compressor;
use WPEasy\BricksStatic\Sync\HtaccessBuilder;
use WPEasy\BricksStatic\Sync\Manifest;
use WPEasy\BricksStatic\Sync\Runner;
use WPEasy\BricksStatic\Support\Paths;

defined('ABSPATH') || exit;

/**
 * A deliberately separate, much smaller state machine than {@see Runner} —
 * export never renders, never touches a transport, and never records
 * anything against a destination's push state. It only reads the current
 * render manifest (applying that destination's replacers, exactly like a
 * real Sync would) and zips the result.
 *
 * Phases: preparing → gzip (Pro, skipped on Free/unavailable) → packaging →
 * saving → done. Each phase is batched across `tick()` calls to avoid a
 * shared-host execution-time limit on large sites.
 */
final class ExportRunner {

    /**
     * Files gzipped per tick (CPU-bound: read + gzencode + write each).
     */
    private const GZIP_BATCH = 40;

    /**
     * Files added to the zip per tick (cheap: ZipArchive just registers the path).
     */
    private const PACK_BATCH = 60;

    /**
     * Whether an export job is currently active. Used by {@see Runner::start()}
     * to refuse starting a Sync/Check while an export is packaging the same
     * render output.
     */
    public static function is_running(): bool {
        return ExportJob::is_running();
    }

    /**
     * Begin an export for one destination. Uses the CURRENT render only — never
     * triggers one. Mirrors {@see Runner::preview()}'s `needsProcess` contract
     * exactly, so the dashboard can reuse the same "Process first" prompt.
     *
     * @param string $dest_id Destination id ('' → primary).
     * @return array<string,mixed> Snapshot, or a `needsProcess` payload.
     * @throws \RuntimeException If a Sync/Check or another export is already
     *                           running, the destination doesn't exist, or the
     *                           export directory isn't writable.
     */
    public static function start(string $dest_id): array {
        if (!empty(Runner::status()['running'])) {
            throw new \RuntimeException(__('A Sync/Check is currently running — wait for it to finish, then try again.', 'bricks-static'));
        }
        if (self::is_running()) {
            throw new \RuntimeException(__('An Export ZIP is already running — wait for it to finish, then try again.', 'bricks-static'));
        }

        $dest = $dest_id !== '' ? Destinations::get($dest_id) : Destinations::primary();
        if ($dest === null) {
            throw new \RuntimeException(__('That destination could not be found.', 'bricks-static'));
        }

        $has_rendered = !empty(Manifest::load(Manifest::RENDER_OPTION));
        if (!Runner::render_is_current()) {
            return [
                'phase'        => 'needsProcess',
                'needsProcess' => true,
                'destId'       => $dest->id(),
                'destName'     => (string) $dest->get('name'),
                'message'      => $has_rendered
                    ? __('The page list has changed since the last render. Click Process to refresh it, then Export again.', 'bricks-static')
                    : __('Nothing has been rendered yet. Click Process first, then Export.', 'bricks-static'),
                'running'      => false,
            ];
        }

        if (!wp_mkdir_p(Paths::export_dir())) {
            throw new \RuntimeException(__('The export directory is not writable: ', 'bricks-static') . Paths::export_dir());
        }

        ExportJob::clear();
        $job = ExportJob::create($dest->id(), (string) $dest->get('name'));

        return self::snapshot($job);
    }

    /**
     * Process one batch of the active phase.
     *
     * @return array<string,mixed>
     */
    public static function tick(): array {
        $job = ExportJob::load();
        if ($job === null) {
            return ['phase' => 'idle', 'running' => false];
        }

        if (!empty($job->data['cancel'])) {
            self::cleanup_tmp($job);
            $job->data['phase']   = 'cancelled';
            $job->data['message'] = __('Export cancelled.', 'bricks-static');
            $job->save();

            return self::snapshot($job);
        }

        try {
            switch ($job->data['phase']) {
                case 'preparing':
                    self::tick_preparing($job);
                    break;
                case 'gzip':
                    self::tick_gzip($job);
                    break;
                case 'packaging':
                    self::tick_packaging($job);
                    break;
                case 'saving':
                    self::tick_saving($job);
                    break;
                default:
                    break;
            }
        } catch (\Throwable $e) {
            self::cleanup_tmp($job);
            $job->data['phase']   = 'error';
            $job->data['message'] = $e->getMessage();
        }

        $job->save();

        return self::snapshot($job);
    }

    /**
     * Current snapshot without advancing. A running job with no progress for a
     * while is auto-discarded (mirrors {@see Runner::status()}).
     *
     * @return array<string,mixed>
     */
    public static function status(): array {
        $job = ExportJob::load();
        if ($job === null) {
            return ['phase' => 'idle', 'running' => false];
        }

        $running = !in_array($job->data['phase'], ['done', 'error', 'cancelled'], true);
        if ($running && (time() - (int) $job->data['updatedAt']) > self::stale_seconds()) {
            self::cleanup_tmp($job);
            ExportJob::clear();

            return ['phase' => 'idle', 'running' => false];
        }

        return self::snapshot($job);
    }

    /**
     * Request cancellation. Checked at the top of the next {@see tick()} —
     * export is always browser-driven within one process, so (unlike Sync)
     * there's no cross-process cancel flag to write.
     */
    public static function cancel(): void {
        $job = ExportJob::load();
        if ($job === null) {
            return;
        }
        $job->data['cancel'] = true;
        $job->save();
    }

    /**
     * Resolve the destination, apply its replacers to the base render (same
     * content a real Sync would push), and size the gzip phase.
     *
     * @param ExportJob $job Active job.
     */
    private static function tick_preparing(ExportJob $job): void {
        self::cleanup_stale_exports();

        $dest = Destinations::get((string) $job->data['destId']);
        if ($dest === null) {
            throw new \RuntimeException(__('That destination no longer exists.', 'bricks-static'));
        }

        $base     = Manifest::load(Manifest::RENDER_OPTION);
        $manifest = Runner::deploy_manifest_for($base, $dest->id());
        $files    = array_keys($manifest);

        $gzip_total = 0;
        foreach ($files as $relative) {
            if (Compressor::is_compressible($relative)) {
                $gzip_total++;
            }
        }

        $job->data['manifest']   = $manifest;
        $job->data['files']      = $files;
        $job->data['fileCount']  = count($files);
        $job->data['gzipTotal']  = $gzip_total;
        $job->data['packTotal']  = count($files);
        $job->data['finalName']  = self::build_filename((string) $dest->get('name'));
        $job->data['zipTmpPath'] = Paths::export_dir() . '/.tmp-' . wp_generate_password(12, false, false) . '.zip';

        $job->data['phase']   = $gzip_total > 0 ? 'gzip' : 'packaging';
        $job->data['message'] = sprintf(
            /* translators: %d: number of files. */
            __('Preparing export… %d files.', 'bricks-static'),
            count($files)
        );
    }

    /**
     * Write `.gz` siblings for a batch of compressible files. Only reached
     * when the edition gate already passed (gzipTotal > 0); still checks the
     * runtime `gzencode()` availability, since Pro doesn't guarantee zlib.
     *
     * @param ExportJob $job Active job.
     */
    private static function tick_gzip(ExportJob $job): void {
        if (!Compressor::available()) {
            $job->data['gzipNotice'] = __('Gzip is not available on this server (the PHP zlib extension is missing) — the export will not include .gz files.', 'bricks-static');
            $job->data['gzipTotal']  = 0;
            $job->data['phase']      = 'packaging';

            return;
        }

        $files = $job->data['files'];
        $batch = array_slice($files, $job->data['gzipIndex'], self::GZIP_BATCH);

        foreach ($batch as $relative) {
            if (Compressor::is_compressible($relative)) {
                $src = (string) ($job->data['manifest'][$relative]['src'] ?? '');
                if ($src !== '' && is_file($src)) {
                    Compressor::write_sibling($src);
                }
                $job->data['gzipDone']++;
            }
            $job->data['gzipIndex']++;
        }

        if ($job->data['gzipIndex'] >= count($files)) {
            // Advancing to packaging this same tick — set its message now so
            // the badge (already 'packaging') and message never disagree.
            $job->data['phase']   = 'packaging';
            $job->data['message'] = sprintf(
                /* translators: 1: files packaged so far, 2: total files. */
                __('Packaging… %1$d/%2$d files.', 'bricks-static'),
                $job->data['packDone'],
                $job->data['packTotal']
            );

            return;
        }

        $job->data['message'] = sprintf(
            /* translators: 1: files gzipped so far, 2: total files to gzip. */
            __('Creating gzip files… %1$d/%2$d.', 'bricks-static'),
            $job->data['gzipDone'],
            $job->data['gzipTotal']
        );
    }

    /**
     * Add a batch of files (plus their `.gz` sibling, when present) to the
     * zip. Reopens/closes the archive each tick so each batch is flushed to
     * disk rather than held open across HTTP requests.
     *
     * @param ExportJob $job Active job.
     */
    private static function tick_packaging(ExportJob $job): void {
        $zip = new \ZipArchive();
        if ($zip->open($job->data['zipTmpPath'], \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException(__('Could not create the export archive.', 'bricks-static'));
        }

        if ($job->data['packIndex'] === 0) {
            // Both editions get an .htaccess; content self-adjusts (cache-control
            // only on Free, + gzip-serving rules on Pro) — same as a real Sync.
            $zip->addFromString('.htaccess', HtaccessBuilder::htaccess());
        }

        $files = $job->data['files'];
        $batch = array_slice($files, $job->data['packIndex'], self::PACK_BATCH);

        foreach ($batch as $relative) {
            $src   = (string) ($job->data['manifest'][$relative]['src'] ?? '');
            $entry = ltrim($relative, '/');

            if ($src !== '' && is_file($src)) {
                $zip->addFile($src, $entry);

                if (Compressor::is_compressible($relative) && is_file($src . '.gz')) {
                    $zip->addFile($src . '.gz', $entry . '.gz');
                }
            }

            $job->data['packDone']++;
            $job->data['packIndex']++;
        }

        $zip->close();

        if ($job->data['packIndex'] >= count($files)) {
            $job->data['phase'] = 'saving';
        }

        $job->data['message'] = sprintf(
            /* translators: 1: files packaged so far, 2: total files. */
            __('Packaging… %1$d/%2$d files.', 'bricks-static'),
            $job->data['packDone'],
            $job->data['packTotal']
        );
    }

    /**
     * Move the finished temp zip to its final name and mint a single-use
     * download token.
     *
     * @param ExportJob $job Active job.
     */
    private static function tick_saving(ExportJob $job): void {
        $tmp = (string) $job->data['zipTmpPath'];
        if (!is_file($tmp) || filesize($tmp) === 0) {
            throw new \RuntimeException(__('The export archive was not created.', 'bricks-static'));
        }

        $final = Paths::export_dir() . '/' . $job->data['finalName'];
        if (!@rename($tmp, $final)) {
            throw new \RuntimeException(__('Could not save the export archive.', 'bricks-static'));
        }

        $job->data['finalPath'] = $final;
        $job->data['bytes']     = (int) filesize($final);

        self::cleanup_stale_exports();

        $token = bin2hex(random_bytes(24));
        set_transient('bs_export_dl_' . $token, [
            'path'     => $final,
            'filename' => $job->data['finalName'],
        ], 30 * MINUTE_IN_SECONDS);

        $job->data['downloadToken'] = $token;
        $job->data['phase']         = 'done';
        $job->data['message']       = sprintf(
            /* translators: 1: number of files, 2: human-readable size. */
            __('Export ready — %1$d files, %2$s.', 'bricks-static'),
            $job->data['fileCount'],
            size_format($job->data['bytes'])
        );
    }

    /**
     * `{site}-{destination}-{datetime}.zip`, sanitised and falling back to
     * generic segments if either sanitises to an empty string.
     *
     * @param string $dest_name Destination display name.
     */
    private static function build_filename(string $dest_name): string {
        $site = sanitize_title(get_bloginfo('name'));
        if ($site === '') {
            $site = 'site';
        }

        $dest = sanitize_title($dest_name);
        if ($dest === '') {
            $dest = 'destination';
        }

        return $site . '-' . $dest . '-' . gmdate('Y-m-d-His') . '.zip';
    }

    /**
     * Remove finished/partial export zips older than 2 hours — covers both an
     * orphaned `.tmp-*` from a crashed run and a finished zip whose download
     * was never claimed. Opportunistic (called from `preparing`/`saving`); no
     * cron needed since export is manually triggered and infrequent.
     */
    private static function cleanup_stale_exports(): void {
        $dir = Paths::export_dir();
        if (!is_dir($dir)) {
            return;
        }

        foreach ((array) glob($dir . '/*.zip') as $file) {
            if (is_file($file) && (time() - (int) filemtime($file)) > 2 * HOUR_IN_SECONDS) {
                @unlink($file);
            }
        }
    }

    /**
     * Remove a job's in-progress temp zip (cancel/error path).
     *
     * @param ExportJob $job Active job.
     */
    private static function cleanup_tmp(ExportJob $job): void {
        $tmp = (string) ($job->data['zipTmpPath'] ?? '');
        if ($tmp !== '' && is_file($tmp)) {
            @unlink($tmp);
        }
    }

    /**
     * Build the snapshot returned to the REST layer / frontend.
     *
     * @param ExportJob $job Active job.
     * @return array<string,mixed>
     */
    private static function snapshot(ExportJob $job): array {
        $d       = $job->data;
        $running = !in_array($d['phase'], ['done', 'error', 'cancelled'], true);

        return [
            'phase'         => $d['phase'],
            'destId'        => $d['destId'],
            'destName'      => $d['destName'],
            'message'       => $d['message'],
            'gzipDone'      => $d['gzipDone'],
            'gzipTotal'     => $d['gzipTotal'],
            'gzipNotice'    => $d['gzipNotice'],
            'packDone'      => $d['packDone'],
            'packTotal'     => $d['packTotal'],
            'fileCount'     => $d['fileCount'],
            'bytes'         => $d['bytes'],
            'running'       => $running,
            'downloadToken' => $d['downloadToken'],
            'needsProcess'  => false,
            'startedAt'     => $d['startedAt'],
        ];
    }

    /**
     * No-progress window (seconds) before a running export is treated as
     * abandoned. Filter `bs_export_stale_seconds`.
     */
    private static function stale_seconds(): int {
        /**
         * Filters the no-progress window (seconds) before a running export job
         * is treated as abandoned.
         *
         * @param int $seconds Window in seconds.
         */
        return (int) apply_filters('bs_export_stale_seconds', 90);
    }
}
