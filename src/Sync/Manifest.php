<?php
/**
 * Content manifest (per-file hashes) for delta sync.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

use WPEasy\BricksStatic\Render\StableHash;

defined('ABSPATH') || exit;

/**
 * Builds and stores a map of relative path → {size, hash} over the rendered
 * output. Compared against the last pushed manifest (M3) to decide what to
 * upload and whether the destination is in sync. Stored in the DB so a
 * cache purge can't lose it.
 */
final class Manifest {

    /**
     * Option holding the manifest of the most recent local render (base).
     */
    public const RENDER_OPTION = 'bs_render_manifest';

    /**
     * Option holding the manifest for the destination currently being deployed
     * (base render with that destination's text replacements applied to HTML).
     */
    public const DEPLOY_OPTION = 'bs_deploy_manifest';

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
                'hash' => StableHash::of_file($path),
            ];
        }

        ksort($manifest);

        return $manifest;
    }

    /**
     * Build a manifest from an upload plan (relative path => source file path).
     *
     * Each entry records the destination size/hash plus the local source to read
     * at upload time — a cache file (pages, rewritten CSS) or a source-tree file
     * (binary assets, never copied).
     *
     * @param array<string,string> $plan Relative path => absolute source file.
     * @return array<string,array{size:int,hash:string,src:string}>
     */
    public static function from_plan(array $plan): array {
        $manifest = [];

        foreach ($plan as $relative => $source) {
            if (!is_file($source)) {
                continue;
            }

            $manifest[$relative] = [
                'size' => (int) filesize($source),
                'hash' => StableHash::of_file($source),
                'src'  => wp_normalize_path($source),
            ];
        }

        ksort($manifest);

        return $manifest;
    }

    /**
     * Reduce a render manifest to the pushed form (size + hash only; the local
     * `src` is irrelevant to what now lives on the destination).
     *
     * @param array<string,array{size:int,hash:string,src?:string}> $render Render manifest.
     * @return array<string,array{size:int,hash:string}>
     */
    public static function pushed_from(array $render): array {
        $pushed = [];
        foreach ($render as $relative => $meta) {
            $pushed[$relative] = ['size' => (int) $meta['size'], 'hash' => (string) $meta['hash']];
        }

        return $pushed;
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
