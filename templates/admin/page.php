<?php
/**
 * Admin page template (placeholder).
 *
 * Rendered inside wp-admin. The `.bs` root container scopes the base
 * framework tokens and styles from assets/css/bs-framework.css.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="wrap">
	<div class="bs">
		<div class="bs-stack bs-stack--lg" style="max-width: 48rem; padding: var(--bs-space--lg) 0;">
			<div class="bs-stack bs-stack--xs">
				<h1><?php echo esc_html__('Bricks Static', 'bricks-static'); ?></h1>
				<p style="color: var(--bs-color-text--muted); font-size: var(--bs-text--lg);">
					<?php echo esc_html__('Generate and serve static HTML versions of Bricks-built pages for performance.', 'bricks-static'); ?>
				</p>
			</div>

			<hr>

			<div
				class="bs-stack bs-stack--sm"
				style="
					padding: var(--bs-space--lg);
					background: var(--bs-color-surface--raised);
					border: var(--bs-border--1) solid var(--bs-color-border);
					border-radius: var(--bs-radius--lg);
					box-shadow: var(--bs-shadow--sm);
				"
			>
				<h2><?php echo esc_html__('Coming soon', 'bricks-static'); ?></h2>
				<p style="color: var(--bs-color-text--muted);">
					<?php echo esc_html__('This is a placeholder page. The static export controls will live here.', 'bricks-static'); ?>
				</p>
			</div>
		</div>
	</div>
</div>
