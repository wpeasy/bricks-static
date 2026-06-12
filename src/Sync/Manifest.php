<?php
/**
 * Content manifest (per-file hashes) for delta sync.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

defined('ABSPATH') || exit;

/**
 * Builds and stores a map of relative path → {size, hash} over the rendered
 * output. Compared against the last pushed manifest (M3) to decide what to
 * upload and whether the destination is in sync. Stored in the DB so a
 * cache purge can't lose it.
 */
final class Manifest {

    /**
     * Option holding the manifest of the most recent local render.
     */
    public const RENDER_OPTION = 'bs_render_manifest';

    /**
     * Option holding the manifest of the last successful push.
     */
    public const PUSHED_OPTION = 'bs_pushed_manifest';

    /**
     * Build a manifest by walking a directory.
     *
     * Skips `.gz` siblings (derived from their source).
     *
     * @param string $dir Absolute directory to scan.
     * @return array<string,array{size:int,hash:string}>
     */
    public static function build(string $dir): array {
        if (!is_dir($dir)) {
            return [];
        }

        $manifest = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        $root = rtrim(wp_normalize_path($dir), '/') . '/';

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = wp_normalize_path($file->getPathname());
            if (substr($path, -3) === '.gz') {
                continue;
            }

            $relative = ltrim(str_replace($root, '', $path), '/');

            $manifest[$relative] = [
                'size' => (int) $file->getSize(),
                'hash' => (string) md5_file($path),
            ];
        }

        ksort($manifest);

        return $manifest;
    }

    /**
     * Save a named manifest option.
     *
     * @param string                                       $option   Option name.
     * @param array<string,array{size:int,hash:string}>    $manifest Manifest data.
     */
    public static function save(string $option, array $manifest): void {
        update_option($option, $manifest, false);
    }

    /**
     * Load a named manifest option.
     *
     * @param string $option Option name.
     * @return array<string,array{size:int,hash:string}>
     */
    public static function load(string $option): array {
        $value = get_option($option, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Compute the changed/new/removed paths between two manifests.
     *
     * @param array<string,array{size:int,hash:string}> $from Baseline (e.g. pushed).
     * @param array<string,array{size:int,hash:string}> $to   Target (e.g. render).
     * @return array{changed:array<int,string>,removed:array<int,string>}
     */
    public static function diff(array $from, array $to): array {
        $changed = [];
        foreach ($to as $path => $meta) {
            if (!isset($from[$path]) || $from[$path]['hash'] !== $meta['hash']) {
                $changed[] = $path;
            }
        }

        $removed = array_values(array_diff(array_keys($from), array_keys($to)));

        return ['changed' => $changed, 'removed' => $removed];
    }
}
