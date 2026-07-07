<?php
/**
 * Video deploy replacer (Pro) — swaps local/embed video sources and rewrites
 * embed origins to the destination at deploy time.
 *
 * @package WPEasy\BricksStaticPro
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Render;

use WPEasy\BricksStatic\Render\UrlRewriter;
use WPEasy\BricksStatic\Settings\Destination;
use WPEasy\BricksStatic\Support\Url;
use WPEasy\BricksStatic\Sync\DeployReplacer;

defined('ABSPATH') || exit;

/**
 * Applies a destination's video swaps. Active whenever there are swaps OR a
 * destination base URL is known — because every page may need its embed origins
 * (YouTube `origin=` etc.) rewritten to the destination even without a swap.
 */
final class VideoDeployReplacer implements DeployReplacer {

    /**
     * {@inheritDoc}
     */
    public function key(): string {
        return 'video';
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(Destination $dest, string $dest_base): array {
        $videos = $dest->video_replacements();

        // No swaps and no destination origin to rewrite → nothing to do.
        if (empty($videos) && $dest_base === '') {
            return ['ctx' => null, 'files' => []];
        }

        $by_page = []; // page => (fromSrc => final src)
        $files   = []; // manifest key => local source file (uploaded regardless of page)

        foreach ($videos as $v) {
            $page = (string) $v['page'];
            $toId = (int) ($v['toId'] ?? 0);
            if ($toId > 0) {
                $url = (string) wp_get_attachment_url($toId);
                $by_page[$page][$v['from']] = $url !== '' ? UrlRewriter::rewrite($url) : $v['to'];
                $file = (string) get_attached_file($toId);
                $key  = $url !== '' ? Url::to_relative_path($url) : null;
                if ($file !== '' && is_file($file) && $key !== null) {
                    $files[$key] = $file;
                }
            } else {
                $by_page[$page][$v['from']] = $v['to'];
            }
        }

        return ['ctx' => ['byPage' => $by_page, 'base' => $dest_base], 'files' => $files];
    }

    /**
     * {@inheritDoc}
     *
     * The embed-origin fix (YouTube `origin=`) runs on EVERY page via the base
     * URL; per-page swaps apply only to the page they were saved for.
     */
    public function apply(string $html, $ctx, string $relative): string {
        $swaps = (array) ($ctx['byPage'][$relative] ?? []);

        return VideoReplacer::apply($html, $swaps, (string) $ctx['base']);
    }

    /**
     * {@inheritDoc}
     */
    public function signature(Destination $dest) {
        return $dest->video_replacements();
    }
}
