<?php
/**
 * Media deploy replacer (Pro) — swaps media URLs for WordPress library items at
 * deploy time and queues the replacement files (full + responsive variants) for
 * upload.
 *
 * @package WPEasy\BricksStaticPro
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Render;

use WPEasy\BricksStatic\Render\UrlRewriter;
use WPEasy\BricksStatic\Settings\Destination;
use WPEasy\BricksStatic\Support\Paths;
use WPEasy\BricksStatic\Support\Url;
use WPEasy\BricksStatic\Sync\DeployReplacer;

defined('ABSPATH') || exit;

/**
 * Applies a destination's media swaps and contributes the swapped-in attachment
 * files to the deploy manifest. (Lifted from the monolithic Runner into the Pro
 * pipeline; behaviour is unchanged.)
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
     */
    public function prepare(Destination $dest, string $dest_base): array {
        $media = $dest->media_replacements();
        if (empty($media)) {
            return ['ctx' => null, 'files' => []];
        }

        $swaps = []; // fromUrl => ['to' => …, 'srcset' => …]
        $files = []; // manifest key => local source file

        foreach ($media as $m) {
            $from = $m['from'];
            $toId = (int) ($m['toId'] ?? 0);

            if ($toId > 0 && wp_attachment_is_image($toId)) {
                $swaps[$from] = [
                    'to'     => UrlRewriter::rewrite((string) wp_get_attachment_url($toId)),
                    'srcset' => self::attachment_srcset($toId),
                ];
                foreach (self::attachment_files($toId) as $vurl => $vpath) {
                    $key = Url::to_relative_path($vurl);
                    if ($key !== null && is_file($vpath)) {
                        $files[$key] = $vpath;
                    }
                }
            } else {
                // No attachment id (or not an image) — swap the URL verbatim.
                $swaps[$from] = ['to' => UrlRewriter::rewrite($m['to']), 'srcset' => ''];
                $src = Paths::source_file($m['to']);
                $key = Url::to_relative_path($m['to']);
                if ($src !== null && is_file($src) && $key !== null) {
                    $files[$key] = $src;
                }
            }
        }

        return ['ctx' => $swaps, 'files' => $files];
    }

    /**
     * {@inheritDoc}
     */
    public function apply(string $html, $ctx): string {
        return MediaReplacer::apply($html, $ctx);
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
