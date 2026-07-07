<?php
/**
 * Vite manifest helper — entry lookup + transitive CSS enqueuing.
 *
 * Vite splits CSS shared by more than one entry (e.g. the SyncPanel modal used by
 * both the frontend FAB and the editor list) into a separate chunk. Browsers do
 * NOT auto-load a chunk's CSS when its JS is imported, so an enqueue that only
 * emits the entry's own `css` array misses it and the component renders unstyled.
 * `enqueue_css()` walks the entry's transitive `imports` and emits every chunk's
 * CSS, with hash-stripped handles so they stay stable across builds.
 *
 * @package WPEasy\BricksStatic
 * @since   1.0.2
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

defined('ABSPATH') || exit;

/**
 * Reads the Free Vite manifest (assets/dist/.vite/manifest.json).
 */
final class Assets {

    /**
     * Decoded manifest (cached per request).
     *
     * @var array<string,mixed>|null
     */
    private static ?array $manifest = null;

    /**
     * Whether the manifest has been read this request.
     */
    private static bool $loaded = false;

    /**
     * Load + cache the manifest.
     *
     * @return array<string,mixed>|null
     */
    public static function manifest(): ?array {
        if (!self::$loaded) {
            self::$loaded = true;
            $path = BS_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';
            if (is_readable($path)) {
                $decoded        = json_decode((string) file_get_contents($path), true);
                self::$manifest = is_array($decoded) ? $decoded : null;
            }
        }

        return self::$manifest;
    }

    /**
     * A manifest entry by input key.
     *
     * @param string $key Vite input key.
     * @return array<string,mixed>|null
     */
    public static function entry(string $key): ?array {
        $m     = self::manifest();
        $entry = $m ? ($m[$key] ?? null) : null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * Every CSS file for an entry — its own plus all transitively imported
     * chunks' CSS, deduped and in dependency order.
     *
     * @param string $key Vite input key.
     * @return array<int,string>
     */
    public static function css_files(string $key): array {
        $m = self::manifest();
        if ($m === null) {
            return [];
        }

        $seen = [];
        $css  = [];
        $walk = static function (string $k) use (&$walk, $m, &$seen, &$css): void {
            if (isset($seen[$k]) || !isset($m[$k]) || !is_array($m[$k])) {
                return;
            }
            $seen[$k] = true;
            foreach ((array) ($m[$k]['imports'] ?? []) as $import) {
                $walk((string) $import);
            }
            foreach ((array) ($m[$k]['css'] ?? []) as $file) {
                $css[(string) $file] = true;
            }
        };
        $walk($key);

        return array_keys($css);
    }

    /**
     * The shared ab-ui base stylesheet (`app-*.css`) that the dashboard/editor/docs
     * entries import. Vite attributes it only to the entries that `import`
     * '@wpeasy/ab-ui/styles' in their own main.ts — NOT to a shared chunk — so a
     * separate entry (the frontend) can't reach it via {@see css_files()}. We
     * recover it as the CSS common to two known ab-ui entries. Empty if unbuilt.
     *
     * @return array<int,string>
     */
    public static function ab_ui_css_files(): array {
        $a = self::css_files('src-svelte/dashboard/main.ts');
        $b = self::css_files('src-svelte/editor/main.ts');

        return array_values(array_intersect($a, $b));
    }

    /**
     * Enqueue the shared ab-ui base stylesheet (see {@see ab_ui_css_files()}), for
     * a context (the frontend FAB) whose entry doesn't get it attributed by Vite.
     *
     * @param array<int,string> $deps Style dependencies.
     */
    public static function enqueue_ab_ui_css(array $deps = []): void {
        foreach (self::ab_ui_css_files() as $file) {
            $base   = (string) preg_replace('/-[A-Za-z0-9_-]{6,}$/', '', basename((string) $file, '.css'));
            $handle = 'bs-css-' . sanitize_key($base !== '' ? $base : $file);
            wp_enqueue_style($handle, BS_PLUGIN_URL . 'assets/dist/' . $file, $deps, BS_VERSION);
        }
    }

    /**
     * Enqueue every CSS file for an entry (entry + transitive chunks). Handles are
     * derived from the file name with the build hash stripped, so they're stable.
     *
     * @param string             $key  Vite input key.
     * @param array<int,string>  $deps Style dependencies (style handles to load first).
     */
    public static function enqueue_css(string $key, array $deps = []): void {
        foreach (self::css_files($key) as $file) {
            $base   = basename((string) $file, '.css');
            $base   = (string) preg_replace('/-[A-Za-z0-9_-]{6,}$/', '', $base); // strip hash
            $handle = 'bs-css-' . sanitize_key($base !== '' ? $base : $file);
            wp_enqueue_style($handle, BS_PLUGIN_URL . 'assets/dist/' . $file, $deps, BS_VERSION);
        }
    }
}
