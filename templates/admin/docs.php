<?php
/**
 * Documentation page template.
 *
 * Hosts the Svelte docs app. The `.bs` root container scopes the base framework
 * tokens/styles; the app mounts into #bs-docs.
 *
 * @package WPEasy\ShipStatic
 * @since   0.0.1
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="wrap">
	<div class="bs">
		<div id="bs-docs">
			<noscript>
				<?php echo esc_html__('ShipStatic documentation requires JavaScript.', 'shipstatic'); ?>
			</noscript>
			<p style="color: var(--bs-color-text--muted);">
				<?php echo esc_html__('Loading…', 'shipstatic'); ?>
			</p>
		</div>
	</div>
</div>
