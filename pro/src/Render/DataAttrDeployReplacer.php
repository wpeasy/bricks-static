<?php
/**
 * Data-attribute deploy replacer (Pro) — rewrites data-* attribute values at
 * deploy time.
 *
 * @package WPEasy\BricksStaticPro
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Render;

use WPEasy\BricksStatic\Settings\Destination;
use WPEasy\BricksStatic\Sync\DeployReplacer;

defined('ABSPATH') || exit;

/**
 * Applies a destination's data-* attribute value swaps.
 */
final class DataAttrDeployReplacer implements DeployReplacer {

    /**
     * {@inheritDoc}
     */
    public function key(): string {
        return 'data';
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(Destination $dest, string $dest_base): array {
        $data = $dest->data_replacements();
        if (empty($data)) {
            return ['ctx' => null, 'files' => []];
        }

        return ['ctx' => $data, 'files' => []];
    }

    /**
     * {@inheritDoc}
     */
    public function apply(string $html, $ctx): string {
        return DataAttrReplacer::apply($html, $ctx);
    }

    /**
     * {@inheritDoc}
     */
    public function signature(Destination $dest) {
        return $dest->data_replacements();
    }
}
