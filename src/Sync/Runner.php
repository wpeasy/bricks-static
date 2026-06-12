<?php
/**
 * Batched sync orchestrator.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\Discovery\UrlCollector;
use WPEasy\BricksStatic\Render\AssetExtractor;
use WPEasy\BricksStatic\Render\PageRenderer;
use WPEasy\BricksStatic\Render\UrlRewriter;
use WPEasy\BricksStatic\Support\Paths;
use WPEasy\BricksStatic\Support\Url;

defined('ABSPATH') || exit;

/**
 * Drives a run as resumable batches: collect → render → assets → finalize.
 * Each tick() does a bounded slice of work and returns a UI snapshot. M2 stops
 * at a local render ("check"); M3 adds the upload phase.
 */
final class Runner {

    /**
     * Pages rendered per tick.
     */
    private const PAGE_BATCH = 5;

    /**
     * Assets copied per tick.
     */
    private const ASSET_BATCH = 10;

    /**
     * Start a fresh run: prepare the cache, seed the queue, enter render phase.
     *
     * @param string $type Run type ('check' or 'sync').
     * @return array<string,mixed> Snapshot.
     * @throws \RuntimeException If the cache directory is not writable.
     */
    public static function start(string $type = 'check'): array {
        if (!Paths::ensure()) {
            throw new \RuntimeException('The staging cache directory is not writable: ' . Paths::cache_dir());
        }

        self::reset_output();
        Job::clear();

        $job = Job::create($type);
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
     * Process one batch of the active job.
     *
     * @return array<string,mixed> Snapshot.
     */
    public static function tick(): array {
        $job = Job::load();
        if ($job === null) {
            return ['phase' => 'idle'];
        }

        if (!empty($job->data['cancel'])) {
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
        }

        return self::snapshot($job);
    }

    /**
     * Request cancellation of the active job.
     */
    public static function cancel(): void {
        $job = Job::load();
        if ($job !== null) {
            $job->data['cancel'] = true;
            $job->save();
        }
    }

    /**
     * Render a batch of queued pages.
     *
     * @param Job $job Active job.
     */
    private static function tick_render(Job $job): void {
        $batch = array_splice($job->data['queue']['pages'], 0, self::PAGE_BATCH);

        foreach ($batch as $url) {
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

                $job->data['counts']['bytes'] += self::write($relative, UrlRewriter::rewrite($result['body']));
                $job->data['counts']['pagesDone']++;

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
            $job->data['message']         = 'Copying assets…';
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
            try {
                $relative = Url::to_relative_path($url);
                if ($relative === null) {
                    $job->skip($url, 'unmappable');
                    continue;
                }

                $result = PageRenderer::fetch_asset($url);
                if ($result['code'] !== 200) {
                    $job->skip($url, 'HTTP ' . $result['code']);
                    continue;
                }

                // Safety net: a page that slipped into the asset queue (e.g. a
                // rel="canonical" link) must not overwrite its rewritten page file.
                if (stripos($result['contentType'], 'html') !== false) {
                    continue;
                }

                $body = $result['body'];

                // CSS: rewrite origins and discover nested url()/@import assets.
                if (self::is_css($relative, $result['contentType'])) {
                    foreach (AssetExtractor::extract_css_assets($body, $url) as $nested) {
                        $job->enqueue_asset($nested);
                    }
                    $body = UrlRewriter::rewrite($body);
                }

                $job->data['counts']['bytes'] += self::write($relative, $body);
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
        $manifest = Manifest::build(Paths::output_dir());
        Manifest::save(Manifest::RENDER_OPTION, $manifest);

        $job->data['counts']['files'] = count($manifest);
        $job->data['phase']           = 'done';
        $job->data['message']         = 'Done.';
        $job->save();
    }

    /**
     * Write a file under the output directory (with gzip sibling), return bytes.
     *
     * @param string $relative Relative file path.
     * @param string $content  File contents.
     * @return int Bytes written (0 on failure).
     */
    private static function write(string $relative, string $content): int {
        $path = Paths::output_dir() . '/' . $relative;
        $dir  = dirname($path);

        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return 0;
        }

        $bytes = file_put_contents($path, $content);
        if ($bytes === false) {
            return 0;
        }

        if (Compressor::is_compressible($relative)) {
            Compressor::write_sibling($path);
        }

        return (int) $bytes;
    }

    /**
     * Whether a file is CSS (by extension or content type).
     *
     * @param string $relative     Relative path.
     * @param string $content_type Response content type.
     */
    private static function is_css(string $relative, string $content_type): bool {
        return strtolower(pathinfo($relative, PATHINFO_EXTENSION)) === 'css'
            || stripos($content_type, 'text/css') !== false;
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
                'pages'  => count($d['queue']['pages']),
                'assets' => count($d['queue']['assets']),
            ],
            'errorCount'   => count($d['errors']),
            'skippedCount' => count($d['skipped']),
            'errors'       => array_slice($d['errors'], -25),
            'skipped'      => array_slice($d['skipped'], -25),
            'startedAt'    => $d['startedAt'],
            'updatedAt'    => $d['updatedAt'],
            'running'      => !in_array($d['phase'], ['done', 'error', 'cancelled', 'idle'], true),
        ];
    }
}
