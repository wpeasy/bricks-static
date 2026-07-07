<?php
/**
 * Media deploy replacer — swaps media URLs for WordPress library items at deploy
 * time, PER PAGE, and queues the replacement files (full + responsive variants)
 * for upload.
 *
 * @package WPEasy\BricksStatic
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

use WPEasy\BricksStatic\Media\MediaCollector;
use WPEasy\BricksStatic\Settings\Destination;
use WPEasy\BricksStatic\Support\Paths;
use WPEasy\BricksStatic\Support\Url;
use WPEasy\BricksStatic\Sync\DeployReplacer;

defined('ABSPATH') || exit;

/**
 * Applies a destination's PER-PAGE media swaps and contributes the swapped-in
 * attachment files to the deploy manifest. Swaps are grouped by the page they
 * belong to; {@see apply()} only rewrites the page it's handed.
 */
final class MediaDeployReplacer implements DeployReplacer {

    /**
     * {@inheritDoc}
     */
    public function key(): string {
        return 'media';
    }

    /**
     * {@inheritDoc}
     *
     * ctx = [ pageRelativePath => [ originalUrl => {to, srcset, width, height} ] ].
     */
    public function prepare(Destination $dest, string $dest_base): array {
        $media = $dest->media_replacements();
        if (empty($media)) {
            return ['ctx' => null, 'files' => []];
        }

        $by_page = []; // page => (fromUrl => swap)
        $files   = []; // manifest key => local source file (uploaded regardless of page)

        foreach ($media as $m) {
            $page = (string) $m['page'];
            $from = (string) $m['from'];
            $toId = (int) ($m['toId'] ?? 0);

            if ($toId > 0 && wp_attachment_is_image($toId)) {
                $to     = UrlRewriter::rewrite((string) wp_get_attachment_url($toId));
                $srcset = self::attachment_srcset($toId);
                $meta   = wp_get_attachment_metadata($toId);
                $width  = is_array($meta) ? (int) ($meta['width'] ?? 0) : 0;
                $height = is_array($meta) ? (int) ($meta['height'] ?? 0) : 0;
                foreach (self::attachment_files($toId) as $vurl => $vpath) {
                    $vkey = Url::to_relative_path($vurl);
                    if ($vkey !== null && is_file($vpath)) {
                        $files[$vkey] = $vpath;
                    }
                }
            } else {
                // No attachment id (or not an image) — swap the URL verbatim.
                $to     = UrlRewriter::rewrite((string) $m['to']);
                $srcset = '';
                $width  = 0;
                $height = 0;
                $src    = Paths::source_file((string) $m['to']);
                $vkey   = Url::to_relative_path((string) $m['to']);
                if ($src !== null && is_file($src) && $vkey !== null) {
                    $files[$vkey] = $src;
                }
            }

            // Rewrite EVERY size of the original, not just the stored URL: resolve
            // it to its source attachment and expand to all variant URLs, so a
            // responsive image swaps cleanly at every width. For images not in the
            // library, only the stored URL is swapped.
            $origins = [$from];
            $from_id = MediaCollector::resolve_attachment($from);
            if ($from_id > 0) {
                foreach (array_keys(self::attachment_files($from_id)) as $vurl) {
                    $origins[] = UrlRewriter::rewrite($vurl);
                }
            }
            foreach (array_unique($origins) as $origin) {
                if ($origin !== '') {
                    $by_page[$page][$origin] = ['to' => $to, 'srcset' => $srcset, 'width' => $width, 'height' => $height];
                }
            }
        }

        return ['ctx' => $by_page, 'files' => $files];
    }

    /**
     * {@inheritDoc}
     */
    public function apply(string $html, $ctx, string $relative): string {
        $swaps = is_array($ctx) ? ($ctx[$relative] ?? []) : [];
        if (empty($swaps)) {
            return $html; // No media swaps saved for this page.
        }

        return MediaReplacer::apply($html, $swaps);
    }

    /**
     * {@inheritDoc}
     */
    public function signature(Destination $dest) {
        return $dest->media_replacements();
    }

    /**
     * Root-relative srcset for a replacement image attachment ('' if none).
     *
     * @param int $id Attachment id.
     */
    private static function attachment_srcset(int $id): string {
        $srcset = wp_get_attachment_image_srcset($id, 'full');

        return is_string($srcset) ? UrlRewriter::rewrite($srcset) : '';
    }

    /**
     * All files for an image attachment (full size + every generated variant),
     * keyed by absolute URL => local file path, so each can be uploaded.
     *
     * @param int $id Attachment id.
     * @return array<string,string>
     */
    private static function attachment_files(int $id): array {
        $files     = [];
        $full_path = (string) get_attached_file($id);
        $full_url  = (string) wp_get_attachment_url($id);
        if ($full_path === '' || $full_url === '') {
            return $files;
        }
        $files[$full_url] = $full_path;

        $meta = wp_get_attachment_metadata($id);
        if (is_array($meta) && !empty($meta['sizes'])) {
            $dir_path = dirname($full_path);
            $base_url = dirname($full_url);
            foreach ((array) $meta['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $files[$base_url . '/' . $size['file']] = $dir_path . '/' . $size['file'];
                }
            }
        }

        return $files;
    }
}
