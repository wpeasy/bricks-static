<?php
/**
 * Link deploy replacer — rewrites <a>/<button> href targets at deploy time.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Render;

use WPEasy\BricksStatic\Settings\Destination;
use WPEasy\BricksStatic\Sync\DeployReplacer;

defined('ABSPATH') || exit;

/**
 * Applies a destination's link swaps (fromHref => toHref).
 */
final class LinkDeployReplacer implements DeployReplacer {

    /**
     * {@inheritDoc}
     */
    public function key(): string {
        return 'link';
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(Destination $dest, string $dest_base): array {
        $links = $dest->link_replacements();
        if (empty($links)) {
            return ['ctx' => null, 'files' => []];
        }

        $swaps = []; // fromHref => toHref
        foreach ($links as $l) {
            $swaps[$l['from']] = $l['to'];
        }

        return ['ctx' => $swaps, 'files' => []];
    }

    /**
     * {@inheritDoc}
     */
    public function apply(string $html, $ctx, string $relative): string {
        // Link replacements are global (whole-export), so the page path is ignored.
        return LinkReplacer::apply($html, $ctx);
    }

    /**
     * {@inheritDoc}
     */
    public function signature(Destination $dest) {
        return $dest->link_replacements();
    }
}
