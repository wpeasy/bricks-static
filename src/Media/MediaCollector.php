<?php
/**
 * Collects the images referenced by ONE rendered page.
 *
 * @package WPEasy\BricksStatic
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Media;

use WPEasy\BricksStatic\Render\UrlRewriter;
use WPEasy\BricksStatic\Sync\Manifest;

defined('ABSPATH') || exit;

/**
 * Media replacement is PER PAGE: the dashboard first picks an exported page, then
 * lists the images on that page for swapping. So this collector works one page at
 * a time — {@see pages()} enumerates the exported pages for the selector, and
 * {@see collect()} scans a single page's cached HTML for its images (grouping the
 * responsive variants of one library image into a single row).
 */
final class MediaCollector {

    /**
     * The exported HTML pages available for media replacement (for the page
     * selector). Each entry is the export-relative path (the stored `page` key,
     * matched at deploy time) plus a friendly display label.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public static function pages(): array {
        $render = Manifest::load(Manifest::RENDER_OPTION);

        $out = [];
        foreach ($render as $relative => $meta) {
            if (substr(strtolower($relative), -5) !== '.html' || !is_file($meta['src'])) {
                continue;
            }
            $out[] = ['value' => (string) $relative, 'label' => self::page_path((string) $relative)];
        }

        usort($out, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $out;
    }

    /**
     * The images referenced by a single exported page.
     *
     * Responsive images expose many URLs for ONE source image (the `src` plus
     * every `srcset`/background variant). We resolve each URL back to its source
     * attachment and group them, so the page shows ONE row per image and a single
     * swap rewrites every size variant. Images not in the media library (external,
     * theme files) can't be grouped — each distinct URL stays its own row, flagged
     * `inLibrary: false` so the UI can warn that other sizes may remain.
     *
     * @param string $page_rel Export-relative page path (e.g. 'about/index.html').
     * @return array<int,array{url:string,thumb:string,alt:string,type:string,inLibrary:bool,variants:int}>
     */
    public static function collect(string $page_rel): array {
        $render = Manifest::load(Manifest::RENDER_OPTION);
        $meta   = $render[$page_rel] ?? null;
        if (!is_array($meta) || substr(strtolower($page_rel), -5) !== '.html' || !is_file($meta['src'])) {
            return [];
        }

        $origin = rtrim((string) home_url(), '/');
        $html   = (string) file_get_contents($meta['src']);

        // group key => [attId, type, alt, urls{}]
        $groups = [];
        foreach (self::media_in($html) as $item) {
            $url    = $item['url'];
            $att_id = self::resolve_attachment($url);
            // Library images group by attachment id; everything else stays keyed by
            // its own URL (no reliable way to find its variants).
            $key = $att_id > 0 ? 'att:' . $att_id : 'url:' . $url;

            if (!isset($groups[$key])) {
                $groups[$key] = ['attId' => $att_id, 'type' => $item['type'], 'alt' => $item['alt'], 'urls' => []];
            }
            if ($item['alt'] !== '' && $groups[$key]['alt'] === '') {
                $groups[$key]['alt'] = $item['alt'];
            }
            $groups[$key]['urls'][$url] = true;
        }

        $out = [];
        foreach ($groups as $g) {
            $url = self::canonical_url(array_keys($g['urls']), (int) $g['attId']);
            $out[] = [
                'url'   => $url,
                // Absolute URL so the admin can show the thumbnail (cached HTML
                // uses root-relative paths after rewriting).
                'thumb' => self::is_absolute($url) ? $url : $origin . '/' . ltrim($url, '/'),
                'alt'   => $g['alt'],
                'type'  => $g['type'],
                // True when the original maps to a library attachment, so a swap
                // covers every size variant; false → single-URL swap only.
                'inLibrary' => (int) $g['attId'] > 0,
                'variants'  => count($g['urls']),
            ];
        }

        usort($out, static fn(array $a, array $b): int => strcasecmp($a['url'], $b['url']));

        return $out;
    }

    /**
     * Resolve a media URL (root-relative, as in the cached HTML, or absolute) back
     * to its source attachment id, stripping any `-WxH` size suffix so a responsive
     * variant resolves to the same attachment as its full size. Returns 0 when the
     * URL isn't a media-library image.
     *
     * @param string $url Media URL.
     */
    public static function resolve_attachment(string $url): int {
        if ($url === '') {
            return 0;
        }

        $abs  = self::is_absolute($url) ? $url : home_url($url);
        // Drop a "-1024x683" style size suffix to get the full-size URL WP stores.
        $full = (string) preg_replace('#-\d+x\d+(?=\.[a-z0-9]+$)#i', '', $abs);

        $id = attachment_url_to_postid($full);
        if ($id === 0 && $full !== $abs) {
            $id = attachment_url_to_postid($abs); // e.g. -scaled.jpg has no -WxH twin
        }

        return (int) $id;
    }

    /**
     * The representative URL for a group: a library image's full-size URL (so the
     * stored swap key is stable across renders), else the largest variant found (or
     * the first non-resized URL).
     *
     * @param array<int,string> $urls   Variant URLs seen for this image.
     * @param int               $att_id Source attachment id (0 if none).
     */
    private static function canonical_url(array $urls, int $att_id): string {
        if ($att_id > 0) {
            $full = (string) wp_get_attachment_url($att_id);
            if ($full !== '') {
                return UrlRewriter::rewrite($full);
            }
        }

        $best      = '';
        $best_area = -1;
        foreach ($urls as $u) {
            if (!preg_match('#-(\d+)x(\d+)(?=\.[a-z0-9]+$)#i', $u, $m)) {
                return $u; // an un-resized URL is the full image
            }
            $area = (int) $m[1] * (int) $m[2];
            if ($area > $best_area) {
                $best_area = $area;
                $best      = $u;
            }
        }

        return $best !== '' ? $best : (string) ($urls[0] ?? '');
    }

    /**
     * Extract image references from one HTML document.
     *
     * @param string $html HTML.
     * @return array<int,array{url:string,alt:string,type:string}>
     */
    private static function media_in(string $html): array {
        $found = [];

        // <img>: real src (prefer src, fall back to common lazy attrs) + alt.
        if (preg_match_all('#<img\b[^>]*>#i', $html, $imgs)) {
            foreach ($imgs[0] as $tag) {
                $src = self::attr($tag, 'src');
                if ($src === '' || strpos($src, 'data:') === 0) {
                    $src = self::attr($tag, 'data-src') ?: self::attr($tag, 'data-lazy-src');
                }
                if ($src === '' || strpos($src, 'data:') === 0) {
                    continue;
                }
                $alt     = self::attr($tag, 'alt');
                $found[] = ['url' => $src, 'alt' => $alt, 'type' => 'image'];

                // srcset variants: captured so every size groups under its source
                // image even when a given size only appears in a srcset.
                $srcset = self::attr($tag, 'srcset');
                if ($srcset !== '') {
                    foreach (explode(',', $srcset) as $cand) {
                        $u = trim((string) preg_replace('#\s+\d+[wx]$#', '', trim($cand)));
                        if ($u !== '' && strpos($u, 'data:') !== 0) {
                            $found[] = ['url' => $u, 'alt' => $alt, 'type' => 'image'];
                        }
                    }
                }
            }
        }

        // CSS background images: url(...) in inline <style> blocks (Bricks emits
        // element backgrounds there) and in style="" attributes. The deploy
        // MediaReplacer rewrites these literally in the HTML, so any that point at
        // an image file are swappable too. (Backgrounds in external .css files
        // aren't listed — the replacer only transforms HTML pages.)
        if (preg_match_all('#url\(\s*([\'"]?)(.+?)\1\s*\)#i', $html, $urls, PREG_SET_ORDER)) {
            foreach ($urls as $u) {
                // Decode entities (style="" attrs encode quotes/ampersands) and strip
                // any quotes so the stored URL is the clean, matchable path.
                $val = trim(html_entity_decode(trim($u[2]), ENT_QUOTES | ENT_HTML5), "\"' ");
                if ($val === '' || strpos($val, 'data:') === 0) {
                    continue;
                }
                // Images only — skip fonts, SVG filter refs, gradients, etc.
                if (!preg_match('#\.(jpe?g|png|webp|gif|svg|avif)(\?|\#|$)#i', $val)) {
                    continue;
                }
                $found[] = ['url' => $val, 'alt' => '', 'type' => 'image'];
            }
        }

        // Video (local <video>/<source> and embeds) is handled by the Videos panel
        // (Pro) — see VideoCollector — so Media stays images only.

        return $found;
    }

    /**
     * Read an attribute value from a tag string.
     *
     * @param string $tag  Tag markup.
     * @param string $name Attribute name.
     */
    private static function attr(string $tag, string $name): string {
        if (preg_match('#\b' . preg_quote($name, '#') . '\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag, $m)) {
            return html_entity_decode($m[2] !== '' ? $m[2] : ($m[3] ?? ''), ENT_QUOTES);
        }

        return '';
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

    /**
     * Whether a URL has a scheme/host (external).
     *
     * @param string $url URL.
     */
    private static function is_absolute(string $url): bool {
        return (bool) preg_match('#^(https?:)?//#i', $url);
    }
}
