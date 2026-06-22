<?php
/**
 * License enforcement for feature gating.
 *
 * @package WPEasy\BricksStaticPro\Licensing
 * @since   0.3.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Licensing;

defined('ABSPATH') || exit;

/**
 * Enforces license requirements for plugin features.
 *
 * States:
 * - unlicensed: No license ever activated, features disabled
 * - valid: Licensed and valid, features enabled
 * - grace: License expired/failed, 7-day grace period, features enabled with warning
 * - expired: License was activated but has expired, features disabled,
 *            admin notice shown, plugin updates blocked
 *
 * @since 0.3.0
 */
final class LicenseEnforcer {

    /**
     * Grace period in days after license check fails.
     */
    private const GRACE_PERIOD_DAYS = 7;

    /**
     * Option key for storing license failure timestamp.
     */
    private const FAILURE_TIMESTAMP_KEY = 'bsp_license_failure_timestamp';

    /**
     * URL parameter for test mode.
     */
    private const TEST_PARAM = 'bsp_license_test';

    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Cached state.
     */
    private ?string $cachedState = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the enforcer - call this early in plugin bootstrap.
     */
    public function init(): void {
        // Register admin notices
        add_action('admin_notices', [$this, 'displayAdminNotice']);

        // Clear failure timestamp when license is successfully activated
        add_action('bsp_license_activated', [$this, 'clearFailureTimestamp']);
    }

    /**
     * Get the license tier.
     *
     * This plugin has no free tier (the free product is a separate plugin),
     * so any usable license is 'premium' and everything else is 'none'.
     *
     * @return string 'none' or 'premium'
     */
    public function getTier(): string {
        return $this->canUseFeatures() ? 'premium' : 'none';
    }

    /**
     * Check if features should be enabled.
     *
     * Features are enabled only while the license is valid or within the
     * grace period. An expired license is a HARD GATE — features turn off.
     *
     * @return bool True if features can run.
     */
    public function canUseFeatures(): bool {
        $state = $this->getState();
        return in_array($state, ['valid', 'grace'], true);
    }

    /**
     * Check if plugin updates should be allowed.
     *
     * Updates are only available with a valid or grace-period license.
     *
     * @return bool True if updates are allowed.
     */
    public function canUpdate(): bool {
        $state = $this->getState();
        return in_array($state, ['valid', 'grace'], true);
    }

    /**
     * Get the current license enforcement state.
     *
     * @return string 'unlicensed', 'valid', 'grace', or 'expired'
     */
    public function getState(): string {
        // Local dev override: define('BSP_LICENSE_DEV', true) in wp-config.php to
        // force a valid license on EVERY request (page, plugins_loaded AND REST),
        // with no current-user or URL-param dependency. Unlike the ?bsp_license_test
        // page param, this keeps the edition consistent across all hooks, so the Pro
        // bundle enqueues and the Pro REST routes register. Never true in production.
        if (defined('BSP_LICENSE_DEV') && BSP_LICENSE_DEV) {
            return 'valid';
        }

        // Check for test mode override
        $testState = $this->getTestState();
        if ($testState !== null) {
            return $testState;
        }

        // Use cached state if available
        if ($this->cachedState !== null) {
            return $this->cachedState;
        }

        $this->cachedState = $this->determineState();
        return $this->cachedState;
    }

    /**
     * Determine the actual license state.
     *
     * @return string
     */
    private function determineState(): string {
        try {
            $licensing = FluentLicensing::getInstance();
            $status = $licensing->getStatus();

            if (is_wp_error($status)) {
                return $this->handleLicenseFailure();
            }

            $licenseKey = $status['license_key'] ?? '';
            $licenseStatus = $status['status'] ?? 'unregistered';

            // No license key entered
            if (empty($licenseKey)) {
                return 'unlicensed';
            }

            // Valid/active license
            if (in_array($licenseStatus, ['valid', 'active'], true)) {
                // Clear any failure timestamp since license is now valid
                $this->clearFailureTimestamp();
                return 'valid';
            }

            // License exists but is invalid/expired - check grace period
            return $this->handleLicenseFailure();

        } catch (\Exception $e) {
            // Licensing not initialized - treat as unlicensed
            return 'unlicensed';
        }
    }

    /**
     * Handle license check failure - manage grace period.
     *
     * @return string 'grace' or 'expired'
     */
    private function handleLicenseFailure(): string {
        $failureTimestamp = get_option(self::FAILURE_TIMESTAMP_KEY, 0);

        // First failure - record timestamp and start grace period
        if (empty($failureTimestamp)) {
            $failureTimestamp = time();
            update_option(self::FAILURE_TIMESTAMP_KEY, $failureTimestamp, false);
        }

        // Calculate days since failure
        $daysSinceFailure = (time() - $failureTimestamp) / DAY_IN_SECONDS;

        if ($daysSinceFailure <= self::GRACE_PERIOD_DAYS) {
            return 'grace';
        }

        return 'expired';
    }

    /**
     * Get remaining grace period days.
     *
     * @return int Days remaining, 0 if not in grace period.
     */
    public function getGraceDaysRemaining(): int {
        $failureTimestamp = get_option(self::FAILURE_TIMESTAMP_KEY, 0);

        if (empty($failureTimestamp)) {
            return 0;
        }

        $daysSinceFailure = (time() - $failureTimestamp) / DAY_IN_SECONDS;
        $remaining = self::GRACE_PERIOD_DAYS - $daysSinceFailure;

        return max(0, (int) ceil($remaining));
    }

    /**
     * Clear the failure timestamp (called when license is activated).
     */
    public function clearFailureTimestamp(): void {
        delete_option(self::FAILURE_TIMESTAMP_KEY);
        $this->cachedState = null;
    }

    /**
     * Clear the cached state (call after licensing is registered).
     *
     * This is needed because Plugin::init() runs on 'after_setup_theme'
     * which may check license state before FluentLicensing is registered
     * on the 'init' hook.
     */
    public function clearCache(): void {
        $this->cachedState = null;
    }

    /**
     * Check for test mode URL parameter.
     *
     * @return string|null Test state or null if not in test mode.
     */
    private function getTestState(): ?string {
        if (!is_admin() || !current_user_can('manage_options')) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $testParam = isset($_GET[self::TEST_PARAM]) ? sanitize_text_field(wp_unslash($_GET[self::TEST_PARAM])) : null;

        if ($testParam === null) {
            return null;
        }

        $validStates = ['unlicensed', 'valid', 'grace', 'expired'];

        if (in_array($testParam, $validStates, true)) {
            return $testParam;
        }

        return null;
    }

    /**
     * Display admin notice based on license state.
     */
    public function displayAdminNotice(): void {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }

        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        $state = $this->getState();
        $isTestMode = $this->getTestState() !== null;

        switch ($state) {
            case 'unlicensed':
                $this->renderNotice(
                    'error',
                    $this->getUnlicensedMessage(),
                    $isTestMode
                );
                break;

            case 'expired':
                $this->renderNotice(
                    'warning',
                    $this->getExpiredMessage(),
                    $isTestMode
                );
                break;

            case 'grace':
                $daysRemaining = $this->getGraceDaysRemaining();
                $this->renderNotice(
                    'warning',
                    $this->getGraceMessage($daysRemaining),
                    $isTestMode
                );
                break;

            case 'valid':
                if ($isTestMode) {
                    // No notice for valid license, unless in test mode
                    $this->renderNotice(
                        'success',
                        __('License is valid. All features are enabled.', 'bricks-static-pro'),
                        true
                    );
                }
                break;
        }
    }

    /**
     * Get the message for unlicensed state.
     *
     * @return string
     */
    private function getUnlicensedMessage(): string {
        $slug = defined('BSP_LICENSE_SLUG') ? BSP_LICENSE_SLUG : 'bricks-static-pro';
        $settingsUrl = admin_url('admin.php?page=' . $slug . '-manage-license');
        $purchaseUrl = defined('BSP_LICENSE_PURCHASE_URL') ? BSP_LICENSE_PURCHASE_URL : 'https://brxprod.com/bricks-static/';

        return sprintf(
            /* translators: 1: URL to license settings page, 2: URL to pricing page */
            __('Bricks Static Pro requires a license key. <a href="%1$s">Enter your license key</a> or <a href="%2$s" target="_blank" rel="noopener noreferrer">purchase a license</a>.', 'bricks-static-pro'),
            esc_url($settingsUrl),
            esc_url($purchaseUrl)
        );
    }

    /**
     * Get the message for grace period state.
     *
     * @param int $daysRemaining Days remaining in grace period.
     * @return string
     */
    private function getGraceMessage(int $daysRemaining): string {
        $slug = defined('BSP_LICENSE_SLUG') ? BSP_LICENSE_SLUG : 'bricks-static-pro';
        $settingsUrl = admin_url('admin.php?page=' . $slug . '-manage-license');

        return sprintf(
            /* translators: 1: Number of days, 2: URL to license settings page */
            _n(
                'Bricks Static Pro license issue detected. You have %1$d day remaining to resolve this. <a href="%2$s">Check your license</a>',
                'Bricks Static Pro license issue detected. You have %1$d days remaining to resolve this. <a href="%2$s">Check your license</a>',
                $daysRemaining,
                'bricks-static-pro'
            ),
            $daysRemaining,
            esc_url($settingsUrl)
        );
    }

    /**
     * Get the message for expired license state.
     *
     * Features are disabled and updates are blocked.
     *
     * @return string
     */
    private function getExpiredMessage(): string {
        $slug = defined('BSP_LICENSE_SLUG') ? BSP_LICENSE_SLUG : 'bricks-static-pro';
        $settingsUrl = admin_url('admin.php?page=' . $slug . '-manage-license');
        $renewUrl = defined('BSP_LICENSE_ACCOUNT_URL') ? BSP_LICENSE_ACCOUNT_URL : '';

        $links = sprintf(
            '<a href="%s">%s</a>',
            esc_url($settingsUrl),
            __('Check your license', 'bricks-static-pro')
        );

        if (!empty($renewUrl)) {
            $links .= sprintf(
                ' | <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url($renewUrl),
                __('Renew license', 'bricks-static-pro')
            );
        }

        return sprintf(
            /* translators: %s: Links to license settings and renewal */
            __('Bricks Static Pro: Your license has expired. Features and plugin updates are disabled until you renew. %s', 'bricks-static-pro'),
            $links
        );
    }

    /**
     * Render an admin notice.
     *
     * @param string $type       Notice type: 'error', 'warning', 'success', 'info'.
     * @param string $message    Notice message (can contain HTML).
     * @param bool   $isTestMode Whether this is a test mode notice.
     */
    private function renderNotice(string $type, string $message, bool $isTestMode = false): void {
        $classes = ['notice', 'notice-' . $type];

        if ($isTestMode) {
            $message = '<strong>[TEST MODE]</strong> ' . $message;
        }

        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr(implode(' ', $classes)),
            wp_kses($message, [
                // SECURITY: 'rel' MUST be in the allow-list so target="_blank"
                // links keep their rel="noopener noreferrer". Without it,
                // wp_kses strips rel and the opened tab gets a populated
                // window.opener reference back into wp-admin — a compromised
                // purchase/renewal landing page (or any redirect chain through
                // an ad/affiliate tracker) can window.opener.location =
                // 'https://evil/fake-login' and replace the admin tab with
                // a phishing page.
                'a'      => ['href' => [], 'target' => [], 'rel' => []],
                'strong' => [],
                'em'     => [],
            ])
        );
    }

    /**
     * Get state description for debugging.
     *
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array {
        return [
            'state'              => $this->getState(),
            'tier'               => $this->getTier(),
            'can_use_features'   => $this->canUseFeatures(),
            'can_update'         => $this->canUpdate(),
            'grace_days_remaining' => $this->getGraceDaysRemaining(),
            'failure_timestamp'  => get_option(self::FAILURE_TIMESTAMP_KEY, 0),
            'is_test_mode'       => $this->getTestState() !== null,
            'test_state'         => $this->getTestState(),
        ];
    }
}
