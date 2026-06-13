<?php
/**
 * Collects the media (images, videos) referenced by the rendered site.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Media;

use WPEasy\BricksStatic\Sync\Manifest;

defined('ABSPATH') || exit;

/**
 * Scans the last render's cached HTML for the media each page uses — so the
 * dashboard can list every image/video with its thumbnail, alt text, and the
 * page(s) it appears on, ready for per-destination swapping.
 */
final class MediaCollector {

    /**
     * Build the media index from the current render.
     *
     * @return array<int,array{url:string,thumb:string,alt:string,type:string,pages:array<int,string>}>
     */
    public static function collect(): array {
        $render = Manifest::load(Manifest::RENDER_OPTION);
        $origin = rtrim((string) home_url(), '/');

        // url => [type, alt, pages{}]
        $index = [];

        foreach ($render as $relative => $meta) {
            if (substr(strtolower($relative), -5) !== '.html' || !is_file($meta['src'])) {
                continue;
            }
            $html = (string) file_get_contents($meta['src']);
            $page = self::page_path($relative);

            foreach (self::media_in($html) as $item) {
                $url = $item['url'];
                if (!isset($index[$url])) {
                    $index[$url] = ['type' => $item['type'], 'alt' => $item['alt'], 'pages' => []];
                }
                if ($item['alt'] !== '' && $index[$url]['alt'] === '') {
                    $index[$url]['alt'] = $item['alt'];
                }
                $index[$url]['pages'][$page] = true;
            }
        }

        $out = [];
        foreach ($index as $url => $info) {
            $out[] = [
                'url'   => $url,
                // Absolute URL so the admin can show the thumbnail (cached HTML
                // uses root-relative paths after rewriting).
                'thumb' => self::is_absolute($url) ? $url : $origin . '/' . ltrim($url, '/'),
                'alt'   => $info['alt'],
                'type'  => $info['type'],
                'pages' => array_keys($info['pages']),
            ];
        }

        usort($out, static fn(array $a, array $b): int => strcasecmp($a['url'], $b['url']));

        return $out;
    }

    /**
     * Extract image/video media references from one HTML document.
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
                $found[] = ['url' => $src, 'alt' => self::attr($tag, 'alt'), 'type' => 'image'];
            }
        }

        // <video poster> and <source src> (video/picture sources).
        if (preg_match_all('#<video\b[^>]*>#i', $html, $vids)) {
            foreach ($vids[0] as $tag) {
                $poster = self::attr($tag, 'poster');
                if ($poster !== '') {
                    $found[] = ['url' => $poster, 'alt' => '', 'type' => 'image'];
                }
            }
        }
        if (preg_match_all('#<source\b[^>]*>#i', $html, $srcs)) {
            foreach ($srcs[0] as $tag) {
                $src  = self::attr($tag, 'src');
                $type = self::attr($tag, 'type');
                if ($src !== '' && (stripos($type, 'video') === 0 || preg_match('#\.(mp4|webm|ogg|mov|m4v)(\?|$)#i', $src))) {
                    $found[] = ['url' => $src, 'alt' => '', 'type' => 'video'];
                }
            }
        }

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
