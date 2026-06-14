<?php
/**
 * Collects the data-* attribute values used by the rendered site.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Media;

use WPEasy\BricksStatic\Sync\Manifest;

defined('ABSPATH') || exit;

/**
 * Scans the last render's cached HTML for `data-*="value"` attributes so the
 * dashboard can list each (attribute, value) pair for per-destination value
 * swapping. Framework-internal attributes and random per-render id values are
 * filtered out so the user's own data attributes stand out (both filterable).
 */
final class DataAttrCollector {

    /**
     * Build the data-attribute index from the current render.
     *
     * @return array<int,array{attr:string,value:string,pages:array<int,string>}>
     */
    public static function collect(): array {
        $render = Manifest::load(Manifest::RENDER_OPTION);
        $deny   = array_flip(self::deny());

        // "attr\0value" => [attr, value, pages{}]
        $index = [];

        foreach ($render as $relative => $meta) {
            if (substr(strtolower($relative), -5) !== '.html' || !is_file($meta['src'])) {
                continue;
            }
            $html = (string) file_get_contents($meta['src']);
            $page = self::page_path($relative);

            if (!preg_match_all('#\bdata-([a-z0-9_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $html, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $m) {
                $attr = strtolower($m[1]);
                if (isset($deny[$attr])) {
                    continue;
                }
                $value = html_entity_decode($m[3] !== '' ? $m[3] : ($m[4] ?? ''), ENT_QUOTES);
                if ($value === '' || self::is_random_id($value)) {
                    continue;
                }
                $key = $attr . "\0" . $value;
                if (!isset($index[$key])) {
                    $index[$key] = ['attr' => $attr, 'value' => $value, 'pages' => []];
                }
                $index[$key]['pages'][$page] = true;
            }
        }

        $out = [];
        foreach ($index as $info) {
            $out[] = ['attr' => $info['attr'], 'value' => $info['value'], 'pages' => array_keys($info['pages'])];
        }

        usort($out, static function (array $a, array $b): int {
            return [$a['attr'], $a['value']] <=> [$b['attr'], $b['value']];
        });

        return $out;
    }

    /**
     * Attribute names (without the `data-` prefix) to skip — framework/internal
     * and lazy-media attributes handled elsewhere. Filter `bs_data_attr_deny`.
     *
     * @return array<int,string>
     */
    private static function deny(): array {
        return (array) apply_filters('bs_data_attr_deny', [
            // Bricks internals (random per render).
            'id', 'script-id', 'element-id', 'query-element-id', 'interaction-id',
            'pagination-id', 'brx-loop-start', 'script-args',
            // Lazy-load media (handled by the media/video replacers).
            'src', 'srcset', 'lazy-src', 'lazy-srcset', 'original', 'original-set',
            'sizes', 'bg', 'background', 'background-image', 'bg-image', 'poster',
            'lazy', 'no-lazy',
        ]);
    }

    /**
     * Whether a value looks like a random hex id (Bricks element/script ids), so
     * it can be filtered from the user-facing list.
     *
     * @param string $value Attribute value.
     */
    private static function is_random_id(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{8,}$/i', $value) && (bool) preg_match('/[a-f]/i', $value);
    }

    /**
     * Turn a cached relative file path into a site page path for display.
     *
     * @param string $relative e.g. "about/index.html".
     */
    private static function page_path(string $relative): string {
        $path = '/' . preg_replace('#/?index\.html$#i', '/', $relative);

        return $path === '/' ? '/' : rtrim($path, '/') . '/';
    }
}
