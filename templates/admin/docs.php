<?php
/**
 * Documentation page template.
 *
 * Hosts the Svelte docs app. The `.bs` root container scopes the base framework
 * tokens/styles; the app mounts into #bs-docs.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="wrap">
	<div class="bs">
		<div id="bs-docs">
			<noscript>
				<?php echo esc_html__('Bricks Static documentation requires JavaScript.', 'bricks-static'); ?>
			</noscript>
			<p style="color: var(--bs-color-text--muted);">
				<?php echo esc_html__('Loading…', 'bricks-static'); ?>
			</p>
		</div>
	</div>
</div>
