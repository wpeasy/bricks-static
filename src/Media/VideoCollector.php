<?php
/**
 * Collects the videos referenced by the rendered site: <iframe> embeds
 * (YouTube/Vimeo/Maps) and locally-hosted <video>/<source> files.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Media;

use WPEasy\BricksStatic\Sync\Manifest;

defined('ABSPATH') || exit;

/**
 * Scans the last render's cached HTML for videos, tagging each with a provider so
 * the dashboard can offer the right control: local files get the media library,
 * embeds get a URL/embed-code field. YouTube gets a thumbnail; local video uses
 * its poster.
 */
final class VideoCollector {

    /**
     * Build the video index from the current render.
     *
     * @return array<int,array{url:string,thumb:string,provider:string,title:string,pages:array<int,string>}>
     */
    public static function collect(): array {
        $render = Manifest::load(Manifest::RENDER_OPTION);
        $origin = rtrim((string) home_url(), '/');

        // url => [title, provider, thumb, pages{}]
        $index = [];
        $add   = static function (string $url, string $provider, string $thumb, string $title, string $page) use (&$index): void {
            if ($url === '') {
                return;
            }
            if (!isset($index[$url])) {
                $index[$url] = ['title' => $title, 'provider' => $provider, 'thumb' => $thumb, 'pages' => []];
            }
            if ($index[$url]['title'] === '' && $title !== '') {
                $index[$url]['title'] = $title;
            }
            if ($index[$url]['thumb'] === '' && $thumb !== '') {
                $index[$url]['thumb'] = $thumb;
            }
            $index[$url]['pages'][$page] = true;
        };

        foreach ($render as $relative => $meta) {
            if (substr(strtolower($relative), -5) !== '.html' || !is_file($meta['src'])) {
                continue;
            }
            $html = (string) file_get_contents($meta['src']);
            $page = self::page_path($relative);

            // <iframe> embeds.
            if (preg_match_all('#<iframe\b[^>]*>#i', $html, $iframes)) {
                foreach ($iframes[0] as $tag) {
                    $src = self::attr($tag, 'src');
                    if (!self::is_usable($src)) {
                        continue;
                    }
                    [$provider, $thumb] = self::provider_thumb($src);
                    $add($src, $provider, $thumb, self::attr($tag, 'title'), $page);
                }
            }

            // Local <video> (direct src and/or <source> children); poster = thumb.
            if (preg_match_all('#<video\b([^>]*)>(.*?)</video>#is', $html, $videos, PREG_SET_ORDER)) {
                foreach ($videos as $v) {
                    $vtag   = '<video' . $v[1] . '>';
                    $poster = self::attr($vtag, 'poster');
                    $thumb  = $poster !== '' ? self::absolute($poster, $origin) : '';
                    $title  = self::attr($vtag, 'title');

                    $direct = self::attr($vtag, 'src');
                    if (self::is_usable($direct)) {
                        $add($direct, 'video', $thumb, $title, $page);
                    }
                    if (preg_match_all('#<source\b[^>]*>#i', $v[2], $sources)) {
                        foreach ($sources[0] as $stag) {
                            $ssrc = self::attr($stag, 'src');
                            if (self::is_usable($ssrc)) {
                                $add($ssrc, 'video', $thumb, $title, $page);
                            }
                        }
                    }
                }
            }
        }

        $out = [];
        foreach ($index as $url => $info) {
            $out[] = [
                'url'      => $url,
                'thumb'    => $info['thumb'],
                'provider' => $info['provider'],
                'title'    => $info['title'],
                'pages'    => array_keys($info['pages']),
            ];
        }

        usort($out, static fn(array $a, array $b): int => strcasecmp($a['url'], $b['url']));

        return $out;
    }

    /**
     * Whether a src is a real, swappable target (not an anchor/JS/data/blank URI).
     *
     * @param string $src Raw src.
     */
    private static function is_usable(string $src): bool {
        $src = trim($src);
        if ($src === '') {
            return false;
        }
        foreach (['#', 'about:', 'javascript:', 'data:'] as $skip) {
            if (stripos($src, $skip) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Provider + thumbnail for an iframe embed URL ('' thumb if unknown).
     *
     * @param string $url Embed URL.
     * @return array{0:string,1:string} [provider, thumbnail]
     */
    private static function provider_thumb(string $url): array {
        if (preg_match('#(?:youtube(?:-nocookie)?\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{6,})#i', $url, $m)
            || preg_match('#youtube(?:-nocookie)?\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{6,})#i', $url, $m)
        ) {
            return ['youtube', 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg'];
        }
        if (preg_match('#vimeo\.com/(?:video/)?\d+#i', $url)) {
            return ['vimeo', ''];
        }
        if (preg_match('#(?:google\.[a-z.]+/maps|maps\.google\.)#i', $url)) {
            return ['map', ''];
        }

        return ['embed', ''];
    }

    /**
     * Make a (possibly root-relative) URL absolute for display.
     *
     * @param string $url    URL.
     * @param string $origin Site origin (no trailing slash).
     */
    private static function absolute(string $url, string $origin): string {
        if (preg_match('#^(https?:)?//#i', $url)) {
            return $url;
        }

        return $origin . '/' . ltrim($url, '/');
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
}
