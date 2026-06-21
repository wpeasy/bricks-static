<?php
/**
 * License settings admin page.
 *
 * Renders the license management UI and handles AJAX requests
 * for activation, deactivation, and status checks.
 *
 * @package WPEasy\BricksStaticPro\Licensing
 * @since   0.3.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Licensing;

defined('ABSPATH') || exit;

/**
 * License settings page class.
 *
 * @since 0.3.0
 */
class LicenseSettings {

    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Licensing instance.
     */
    private ?FluentLicensing $licensing = null;

    /**
     * Menu page arguments.
     *
     * @var array<string, mixed>
     */
    private array $menuArgs = [];

    /**
     * Settings configuration.
     *
     * @var array<string, string>
     */
    private array $config = [];

    /**
     * Register the license settings page.
     *
     * @since 0.3.0
     *
     * @param FluentLicensing      $licensing Licensing instance.
     * @param array<string, string> $config   Page configuration.
     * @return self
     */
    public function register(FluentLicensing $licensing, array $config = []): self {
        if (self::$instance) {
            return self::$instance;
        }

        $this->licensing = $licensing;

        $defaultLabels = [
            'menu_title'      => 'License Settings',
            'page_title'      => 'License Settings',
            'title'           => 'License Settings',
            'description'     => 'Manage your license settings for the plugin.',
            'license_key'     => 'License Key',
            'purchase_url'    => '',
            'account_url'     => '',
            'plugin_name'     => 'Bricks Static Pro',
            'action_renderer' => '',
        ];

        $this->config = wp_parse_args($config, $defaultLabels);

        $ajaxPrefix = 'wp_ajax_' . $this->licensing->getConfig('slug') . '_license';

        add_action($ajaxPrefix . '_activate', [$this, 'handleLicenseActivateAjax']);
        add_action($ajaxPrefix . '_deactivate', [$this, 'handleLicenseDeactivateAjax']);
        add_action($ajaxPrefix . '_status', [$this, 'handleLicenseStatusAjax']);

        if (!empty($this->config['action_renderer'])) {
            add_action('fluent_licensing_render_' . $this->config['action_renderer'], [$this, 'renderLicensingContent']);
        }

        self::$instance = $this;

        return self::$instance;
    }

    /**
     * Get the singleton instance.
     *
     * @since 0.3.0
     *
     * @return self
     */
    public static function getInstance(): self {
        return self::$instance ?? new self();
    }

    /**
     * Handle license activation AJAX request.
     *
     * @since 0.3.0
     */
    public function handleLicenseActivateAjax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json(['message' => 'You do not have permission to perform this action.'], 422);
        }

        $nonce      = isset($_POST['_nonce']) ? sanitize_text_field(wp_unslash($_POST['_nonce'])) : '';
        $licenseKey = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

        if (!wp_verify_nonce($nonce, 'fct_license_nonce')) {
            wp_send_json(['message' => 'Invalid nonce. Please try again.'], 422);
        }

        if (!$licenseKey) {
            wp_send_json(['message' => 'Please provide a valid license key.'], 422);
        }

        $currentLicense = $this->licensing->getStatus();

        if (is_array($currentLicense) && ($currentLicense['status'] ?? '') === 'valid' && ($currentLicense['license_key'] ?? '') === $licenseKey) {
            wp_send_json(['message' => 'This license key is already active.'], 200);
        }

        $activated = $this->licensing->activate($licenseKey);

        if (is_wp_error($activated)) {
            wp_send_json([
                'message' => $activated->get_error_message(),
                'status'  => 'api_error',
            ], 422);
        }

        if (($activated['status'] ?? '') !== 'valid') {
            wp_send_json([
                'message' => 'License activation failed. Please check your license key.',
                'status'  => $activated['status'] ?? 'unknown',
            ], 422);
        }

        wp_send_json([
            'message' => 'License activated successfully.',
            'status'  => 'active',
        ], 200);
    }

    /**
     * Handle license deactivation AJAX request.
     *
     * @since 0.3.0
     */
    public function handleLicenseDeactivateAjax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json(['message' => 'You do not have permission to perform this action.'], 422);
        }

        $nonce = isset($_POST['_nonce']) ? sanitize_text_field(wp_unslash($_POST['_nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'fct_license_nonce')) {
            wp_send_json(['message' => 'Invalid nonce. Please try again.'], 422);
        }

        $deactivated = $this->licensing->deactivate();

        wp_send_json([
            'message'            => 'License deactivated successfully.',
            'remote_deactivated' => !is_wp_error($deactivated),
        ]);
    }

    /**
     * Handle license status check AJAX request.
     *
     * @since 0.3.0
     */
    public function handleLicenseStatusAjax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json(['message' => 'You do not have permission to perform this action.'], 422);
        }

        $nonce = isset($_POST['_nonce']) ? sanitize_text_field(wp_unslash($_POST['_nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'fct_license_nonce')) {
            wp_send_json(['message' => 'Invalid nonce. Please try again.'], 422);
        }

        $status = $this->licensing->getStatus(true);

        if (is_wp_error($status)) {
            // SECURITY: WP_Error message comes from FluentLicensing::apiRequest
            // which lifts it verbatim from the remote licensing API JSON body.
            // A compromised brxprod.com / MITM / CDN edge takeover could return
            // a message containing HTML/JS. We previously dropped this into the
            // client's innerHTML via setError(..., true) — full admin XSS. The
            // server now emits a plain-text notice; the client renders via
            // textContent. No isHtml flag.
            wp_send_json([
                'error_notice' => wp_strip_all_tags((string) $status->get_error_message()),
            ]);
            return;
        }

        // Structured payload — client assembles any HTML (the renew button)
        // via DOM construction. No HTML emitted from the server here.
        $notice_text   = '';
        $renewal_url   = '';
        if (!empty($status['is_expired'])) {
            $notice_text = __('Your license has expired. Please renew to continue receiving updates and support.', 'bricks-static-pro');
            if (!empty($status['renewal_url'])) {
                $renewal_url = esc_url_raw((string) $status['renewal_url']);
            }
        } elseif (!empty($status['error_type']) && $status['error_type'] === 'disabled') {
            // SECURITY: $status['message'] is from the remote JSON (potentially
            // HTML) — strip tags so it lands in the client as plain text.
            $notice_text = !empty($status['message'])
                ? wp_strip_all_tags((string) $status['message'])
                : __('Your license has been disabled. Please contact support.', 'bricks-static-pro');
        }

        unset($status['license_key']);

        wp_send_json([
            'error_notice' => $notice_text,
            'renewal_url'  => $renewal_url,
            'remote_data'  => $status,
        ]);
    }

    /**
     * Register an admin menu page for the license settings.
     *
     * @since 0.3.0
     *
     * @param array<string, mixed> $args Menu page arguments.
     * @return self
     */
    public function addPage(array $args): self {
        if (!$this->licensing) {
            return $this;
        }

        $this->menuArgs = wp_parse_args($args, [
            'type'        => 'submenu',
            'page_title'  => $this->config['page_title'] ?? '',
            'menu_title'  => $this->config['menu_title'] ?? '',
            'capability'  => 'manage_options',
            'parent_slug' => 'tools.php',
            'menu_slug'   => $this->licensing->getConfig('slug') . '-manage-license',
            'menu_icon'   => '',
            'position'    => 999,
        ]);

        add_action('admin_menu', [$this, 'createMenuPage'], 999);

        return $this;
    }

    /**
     * Create the admin menu page based on configured type.
     *
     * @since 0.3.0
     */
    public function createMenuPage(): void {
        switch ($this->menuArgs['type']) {
            case 'menu':
                $this->createTopLevelMenuPage();
                break;
            case 'submenu':
                $this->createSubMenuPage();
                break;
            case 'options':
                $this->createOptionsPage();
                break;
        }
    }

    /**
     * Create a top-level menu page.
     *
     * @since 0.3.0
     */
    private function createTopLevelMenuPage(): void {
        add_menu_page(
            $this->menuArgs['page_title'],
            $this->menuArgs['menu_title'],
            $this->menuArgs['capability'],
            $this->menuArgs['menu_slug'],
            [$this, 'renderLicensingContent'],
            $this->menuArgs['menu_icon'] ?: 'dashicons-admin-generic',
            $this->menuArgs['position'] ?? 100
        );
    }

    /**
     * Create an options submenu page.
     *
     * @since 0.3.0
     */
    private function createOptionsPage(): void {
        if (function_exists('add_options_page')) {
            add_options_page(
                $this->menuArgs['page_title'],
                $this->menuArgs['menu_title'],
                $this->menuArgs['capability'],
                $this->menuArgs['menu_slug'],
                [$this, 'renderLicensingContent']
            );
        }
    }

    /**
     * Create a submenu page under a parent menu.
     *
     * @since 0.3.0
     */
    private function createSubMenuPage(): void {
        add_submenu_page(
            $this->menuArgs['parent_slug'],
            $this->menuArgs['page_title'],
            $this->menuArgs['menu_title'],
            $this->menuArgs['capability'],
            $this->menuArgs['menu_slug'],
            [$this, 'renderLicensingContent'],
            $this->menuArgs['position'] ?? 10
        );
    }

    /**
     * Inspect the server's encryption readiness and return a list of
     * alerts to render above the activation form.
     *
     * Activation refuses to store the license key when AUTH_KEY is
     * missing/short or OpenSSL is unavailable (see FluentLicensing::activate
     * + Crypto::encrypt). Surface those preconditions HERE so
     * users see actionable guidance instead of hitting a cryptic server
     * error after submitting their key.
     *
     * Three states (priority order — placeholder is checked first so the
     * user-facing message says "you forgot to REPLACE the placeholder"
     * rather than "your key is too short"):
     *   - 'placeholder' (non-blocking warning) — AUTH_KEY is the literal
     *                     `'put your unique phrase here'` text WordPress ships
     *                     in wp-config-sample.php. Activation is allowed to
     *                     proceed (the user has acknowledged the risk by being
     *                     shown the warning); downstream encrypt() will still
     *                     reject the save with its own error if the placeholder
     *                     is too short for AES-256.
     *   - 'missing' (blocking) — AUTH_KEY undefined / empty / under 32 chars
     *                     and NOT the WP placeholder. Activation is disabled
     *                     because encrypt() will refuse the save.
     *   - 'openssl' (blocking) — PHP OpenSSL extension not loaded. Independent
     *                     of AUTH_KEY.
     *
     * @return array<int, array{level: string, title: string, body: string, steps: array<int, string>, note: string, cta_url: string, cta_text: string, blocking: bool}>
     */
    private function getEncryptionReadinessAlerts(): array {
        $alerts = [];

        $wpPlaceholder = 'put your unique phrase here';
        $saltGenerator = 'https://api.wordpress.org/secret-key/1.1/salt/';
        $hasAuthKey = defined('AUTH_KEY') && is_string(AUTH_KEY) && AUTH_KEY !== '';
        $isPlaceholder = $hasAuthKey && AUTH_KEY === $wpPlaceholder;
        $isMissingOrShort = !$hasAuthKey || strlen(AUTH_KEY) < 32;

        if ($isPlaceholder) {
            $alerts[] = [
                'level' => 'warning',
                'title' => __('Default WordPress security keys detected', 'bricks-static-pro'),
                'body'  => __('Your wp-config.php still contains the placeholder text that ships with a fresh WordPress install ("put your unique phrase here"). Bricks Static Pro encrypts your license key using these WordPress security keys — replacing the placeholder with real, randomly-generated values is strongly recommended before activating.', 'bricks-static-pro'),
                'steps' => [
                    sprintf(
                        /* translators: %s: URL to WordPress.org salt generator */
                        __('Open the WordPress salt generator: <a href="%s" target="_blank" rel="noopener">%s</a>', 'bricks-static-pro'),
                        esc_url($saltGenerator),
                        esc_html($saltGenerator)
                    ),
                    __('Copy the 8 lines it outputs.', 'bricks-static-pro'),
                    __('Open <code>wp-config.php</code> in the root of your WordPress install and replace the existing salt block (the 8 lines starting with AUTH_KEY, SECURE_AUTH_KEY, etc.) with the new lines.', 'bricks-static-pro'),
                    __('Save the file and reload this page.', 'bricks-static-pro'),
                ],
                'note'     => __('Updating these keys logs everyone out of your site (it invalidates session cookies). No data is lost — users just need to sign in again.', 'bricks-static-pro'),
                'cta_url'  => $saltGenerator,
                'cta_text' => __('Generate WordPress Security Keys', 'bricks-static-pro'),
                'blocking' => false,
            ];
        } elseif ($isMissingOrShort) {
            $alerts[] = [
                'level' => 'error',
                'title' => __('License activation is blocked — WordPress security keys are missing', 'bricks-static-pro'),
                'body'  => __('Your wp-config.php is missing the AUTH_KEY security constant (or it\'s too short). Bricks Static Pro uses this key to encrypt your license key at rest, so it\'s never stored in plain text in your database. Activation is paused until this is fixed.', 'bricks-static-pro'),
                'steps' => [
                    sprintf(
                        /* translators: %s: URL to WordPress.org salt generator */
                        __('Open the WordPress salt generator: <a href="%s" target="_blank" rel="noopener">%s</a>', 'bricks-static-pro'),
                        esc_url($saltGenerator),
                        esc_html($saltGenerator)
                    ),
                    __('Copy the 8 lines it outputs.', 'bricks-static-pro'),
                    __('Open <code>wp-config.php</code> in the root of your WordPress install and paste the 8 lines in (replacing any existing salt block, or adding it above the "That\'s all, stop editing" comment).', 'bricks-static-pro'),
                    __('Save the file and reload this page.', 'bricks-static-pro'),
                ],
                'note'     => __('Updating these keys logs everyone out of your site (it invalidates session cookies). No data is lost — users just need to sign in again.', 'bricks-static-pro'),
                'cta_url'  => $saltGenerator,
                'cta_text' => __('Generate WordPress Security Keys', 'bricks-static-pro'),
                'blocking' => true,
            ];
        }

        if (!extension_loaded('openssl')) {
            $alerts[] = [
                'level' => 'error',
                'title' => __('License activation is blocked — OpenSSL PHP extension is not loaded', 'bricks-static-pro'),
                'body'  => __('Your server is missing the OpenSSL PHP extension, which Bricks Static Pro uses to encrypt your license key. The OpenSSL extension is included in PHP by default on virtually every host — it\'s unusual to see it disabled.', 'bricks-static-pro'),
                'steps' => [
                    __('Contact your hosting provider and ask them to enable the OpenSSL PHP extension.', 'bricks-static-pro'),
                    __('If you manage your own server, check that <code>extension=openssl</code> is enabled in your <code>php.ini</code> file.', 'bricks-static-pro'),
                    __('Once enabled, reload this page.', 'bricks-static-pro'),
                ],
                'note'     => '',
                'cta_url'  => '',
                'cta_text' => '',
                'blocking' => true,
            ];
        }

        return $alerts;
    }

    /**
     * Render the license management page.
     *
     * @since 0.3.0
     */
    public function renderLicensingContent(): void {
        if (!$this->licensing) {
            echo '<div class="fct_error"><p>' . esc_html__('Licensing instance is not available.', 'bricks-static-pro') . '</p></div>';
            return;
        }

        $licenseStatus = $this->licensing->getStatus();
        if (is_wp_error($licenseStatus)) {
            $licenseStatus = [
                'status'      => 'error',
                'license_key' => '',
            ];
        }

        $purchaseUrl = $this->config['purchase_url'] ?? '';

        // Pre-flight: detect encryption issues BEFORE rendering the form so
        // the user sees concrete, actionable guidance rather than a cryptic
        // server message after they hit Activate. activate() refuses to store
        // license keys in plaintext when AUTH_KEY is missing or OpenSSL is
        // unavailable — surface that here. Note: a placeholder AUTH_KEY warns
        // but does NOT block the button (per product decision), so we filter
        // by the per-alert 'blocking' flag rather than counting alerts.
        $encryptionAlerts = $this->getEncryptionReadinessAlerts();
        $activationBlocked = false;
        foreach ($encryptionAlerts as $alert) {
            if (!empty($alert['blocking'])) {
                $activationBlocked = true;
                break;
            }
        }
        ?>

        <div class="fct_licensing_wrap">
            <div class="fct_licensing_header">
                <h1><?php echo esc_html($this->config['title']); ?></h1>
                <?php if (!empty($this->config['account_url'])): ?>
                    <a rel="noopener" target="_blank" href="<?php echo esc_url($this->config['account_url']); ?>">Account</a>
                <?php endif; ?>
            </div>

            <div id="fct_license_body" class="fct_licensing_body">
                <?php foreach ($encryptionAlerts as $alert): ?>
                    <div class="fct_alert fct_alert_<?php echo esc_attr($alert['level']); ?>" role="alert">
                        <div class="fct_alert_icon" aria-hidden="true"><?php echo $alert['level'] === 'error' ? '⛔' : '⚠️'; ?></div>
                        <div class="fct_alert_body">
                            <h3 class="fct_alert_title"><?php echo esc_html($alert['title']); ?></h3>
                            <p class="fct_alert_text"><?php echo esc_html($alert['body']); ?></p>
                            <?php if (!empty($alert['steps'])): ?>
                                <ol class="fct_alert_steps">
                                    <?php foreach ($alert['steps'] as $step): ?>
                                        <li><?php echo wp_kses($step, ['a' => ['href' => [], 'target' => [], 'rel' => []], 'code' => []]); ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                            <?php if (!empty($alert['note'])): ?>
                                <p class="fct_alert_note"><?php echo esc_html($alert['note']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($alert['cta_url'])): ?>
                                <p class="fct_alert_cta">
                                    <a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url($alert['cta_url']); ?>">
                                        <?php echo esc_html($alert['cta_text'] ?? __('Learn more', 'bricks-static-pro')); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (($licenseStatus['status'] ?? '') === 'valid'): ?>
                    <h2>Your License is Active<?php if (!empty($licenseStatus['variation_title'])): ?> (<?php echo esc_html($licenseStatus['variation_title']); ?>)<?php endif; ?></h2>
                    <p>Thank you for activating your license for <?php echo esc_html($this->config['plugin_name']); ?>.</p>
                    <p><strong>Status:</strong> <?php echo esc_html(ucfirst($licenseStatus['status'])); ?></p>

                    <?php if (($licenseStatus['expires'] ?? '') !== 'lifetime'): ?>
                        <p><strong>Expires On:</strong> <?php echo esc_html($licenseStatus['expires'] ?? 'N/A'); ?></p>
                    <?php else: ?>
                        <p><strong>Expires On:</strong> Never</p>
                    <?php endif; ?>

                    <p><a id="fct_deactivate_license" href="#">Deactivate License</a></p>

                    <div id="fct_error_wrapper"></div>

                <?php else: ?>
                    <h2>Please Provide the License Key for <?php echo esc_html($this->config['plugin_name']); ?></h2>
                    <div class="fct_licensing_form">
                        <input type="text" name="fct_license_key"
                               value="<?php echo esc_attr($licenseStatus['license_key'] ?? ''); ?>"
                               placeholder="Your License Key"
                               <?php echo $activationBlocked ? 'disabled' : ''; ?> />
                        <button id="license_key_submit" class="button button-primary"
                                <?php echo $activationBlocked ? 'disabled' : ''; ?>>
                            Activate License
                        </button>
                    </div>
                    <?php if ($activationBlocked): ?>
                        <p class="fct_activation_paused_note">
                            <?php esc_html_e('Activation is paused until the issue above is resolved.', 'bricks-static-pro'); ?>
                        </p>
                    <?php endif; ?>

                    <div id="fct_error_wrapper"></div>

                    <?php if ($purchaseUrl): ?>
                        <div class="fct_purchase_wrap">
                            <p>
                                Don't have a license?
                                <a rel="noopener" href="<?php echo esc_url($purchaseUrl); ?>" target="_blank">Purchase License</a>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="fct_loader_item">
                    <svg fill="hsl(228, 97%, 42%)" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="4" cy="12" r="3">
                            <animate id="spinner_qFRN" begin="0;spinner_OcgL.end+0.25s" attributeName="cy"
                                     calcMode="spline" dur="0.6s" values="12;6;12"
                                     keySplines=".33,.66,.66,1;.33,0,.66,.33"/>
                        </circle>
                        <circle cx="12" cy="12" r="3">
                            <animate begin="spinner_qFRN.begin+0.1s" attributeName="cy" calcMode="spline" dur="0.6s"
                                     values="12;6;12" keySplines=".33,.66,.66,1;.33,0,.66,.33"/>
                        </circle>
                        <circle cx="20" cy="12" r="3">
                            <animate id="spinner_OcgL" begin="spinner_qFRN.begin+0.2s" attributeName="cy"
                                     calcMode="spline" dur="0.6s" values="12;6;12"
                                     keySplines=".33,.66,.66,1;.33,0,.66,.33"/>
                        </circle>
                    </svg>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                var licenseBody = document.getElementById('fct_license_body');

                // SECURITY: always use textContent — never innerHTML — for
                // error messages. They originate from the remote licensing
                // API (compromised origin / MITM / CDN edge takeover could
                // inject HTML). Optional renewal_url is rendered as a real
                // <a> element via DOM construction with a hardcoded label.
                function setError(errorMessage, renewalUrl) {
                    var errorWrapper = document.getElementById('fct_error_wrapper');
                    if (!errorMessage) {
                        if (errorWrapper) errorWrapper.innerHTML = '';
                        return;
                    }
                    if (errorWrapper) {
                        var errorDiv = document.createElement('div');
                        errorDiv.className = 'fct_error_notice';

                        var msgP = document.createElement('p');
                        msgP.textContent = errorMessage;
                        errorDiv.appendChild(msgP);

                        // Hardcoded button label + http(s)-validated URL only.
                        // Reject anything that's not http(s) to block
                        // javascript: / data: schemes.
                        if (renewalUrl && /^https?:\/\//i.test(renewalUrl)) {
                            var linkP = document.createElement('p');
                            var link = document.createElement('a');
                            link.href = renewalUrl;
                            link.target = '_blank';
                            link.rel = 'noopener noreferrer';
                            link.className = 'button button-primary fct_renew_url_btn';
                            link.textContent = 'Renew License';
                            linkP.appendChild(link);
                            errorDiv.appendChild(linkP);
                        }

                        errorWrapper.innerHTML = '';
                        errorWrapper.appendChild(errorDiv);
                    } else {
                        alert(errorMessage);
                    }
                }

                function sendAjaxRequest(action, data) {
                    if (licenseBody) licenseBody.classList.add('fct_loading');
                    setError('');

                    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                    var _nonce = '<?php echo esc_js(wp_create_nonce('fct_license_nonce')); ?>';

                    data.action = '<?php echo esc_js($this->licensing->getConfig('slug')); ?>_license_' + action;
                    data._nonce = _nonce;

                    return new Promise(function(resolve, reject) {
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', ajaxUrl, true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function () {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                resolve(JSON.parse(xhr.responseText));
                            } else {
                                reject(JSON.parse(xhr.responseText));
                            }
                        };
                        xhr.onerror = function () {
                            reject({ message: 'Network error occurred while processing the request.' });
                        };
                        xhr.send(new URLSearchParams(data).toString());
                        xhr.onloadend = function () {
                            if (licenseBody) licenseBody.classList.remove('fct_loading');
                        };
                    });
                }

                function activateLicense(licenseKey) {
                    if (!licenseKey) {
                        alert('Please enter a valid license key.');
                        return;
                    }
                    sendAjaxRequest('activate', { license_key: licenseKey })
                        .then(function() { location.reload(); })
                        .catch(function(error) {
                            setError(error.message || 'An error occurred while activating the license.');
                        });
                }

                <?php if (($licenseStatus['status'] ?? 'unregistered') !== 'unregistered'): ?>
                sendAjaxRequest('status', {})
                    .then(function(response) {
                        if (response.error_notice) {
                            setError(response.error_notice, response.renewal_url);
                        }
                    });
                <?php endif; ?>

                var activateBtn = document.getElementById('license_key_submit');
                if (activateBtn) {
                    activateBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        var licenseKey = document.querySelector('input[name="fct_license_key"]').value.trim();
                        activateLicense(licenseKey);
                    });
                }

                var deactivateBtn = document.getElementById('fct_deactivate_license');
                if (deactivateBtn) {
                    deactivateBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        sendAjaxRequest('deactivate', {})
                            .then(function() { window.location.reload(); })
                            .catch(function() { window.location.reload(); });
                    });
                }

                document.querySelectorAll('.update-nag, .notice, #wpbody-content > .updated, #wpbody-content > .error').forEach(function(el) { el.remove(); });
            });
        </script>

        <style>
            .fct_licensing_wrap {
                position: relative;
                max-width: 600px;
                margin: 30px auto;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .fct_loader_item {
                display: none;
            }

            .fct_loading .fct_loader_item {
                display: block;
                position: absolute;
                right: 10px;
                bottom: 0;
            }

            .fct_loader_item svg {
                fill: #686a6b;
                width: 40px;
            }

            .fct_error_notice {
                color: #ff4e16;
                margin-top: 20px;
                font-size: 15px;
            }

            .fct_error_notice p {
                font-size: 15px;
            }

            .fct_purchase_wrap {
                margin-top: 20px;
                display: block;
                overflow: hidden;
            }

            .fct_licensing_header {
                background: #f7fafc;
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .fct_licensing_header a {
                text-decoration: none;
                background: #0073aa;
                color: #fff;
                padding: 2px 12px;
                border-radius: 4px;
            }

            .fct_licensing_header h1 {
                margin: 0;
                font-size: 20px;
                padding: 0;
            }

            .fct_licensing_body {
                padding: 30px 20px;
            }

            .fct_licensing_wrap h2 {
                margin-top: 0;
                font-size: 18px;
                margin-bottom: 10px;
            }

            .fct_licensing_form {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                flex-wrap: wrap;
            }

            .fct_licensing_form input {
                width: 100%;
                padding: 6px 10px;
            }

            .fct_licensing_form input:disabled,
            .fct_licensing_form button:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .fct_activation_paused_note {
                margin: 10px 0 0;
                color: #666;
                font-size: 13px;
                font-style: italic;
            }

            .fct_alert {
                display: flex;
                gap: 12px;
                padding: 16px 18px;
                margin: 0 0 24px;
                border-radius: 6px;
                border: 1px solid;
                background: #fff;
            }

            .fct_alert_error {
                border-color: #d63638;
                background: #fcf0f1;
            }

            .fct_alert_warning {
                border-color: #dba617;
                background: #fcf9e8;
            }

            .fct_alert_icon {
                font-size: 22px;
                line-height: 1;
                flex: 0 0 auto;
            }

            .fct_alert_body {
                flex: 1 1 auto;
                min-width: 0;
            }

            .fct_alert_title {
                margin: 0 0 8px;
                font-size: 15px;
                font-weight: 600;
                color: #1d2327;
            }

            .fct_alert_text {
                margin: 0 0 12px;
                font-size: 14px;
                line-height: 1.5;
                color: #2c3338;
            }

            .fct_alert_steps {
                margin: 0 0 12px 20px;
                padding: 0;
                font-size: 14px;
                line-height: 1.6;
                color: #2c3338;
            }

            .fct_alert_steps li {
                margin-bottom: 4px;
            }

            .fct_alert_steps code {
                background: rgba(0, 0, 0, 0.06);
                padding: 1px 6px;
                border-radius: 3px;
                font-size: 12px;
            }

            .fct_alert_steps a {
                word-break: break-all;
            }

            .fct_alert_note {
                margin: 0 0 12px;
                padding: 8px 12px;
                background: rgba(0, 0, 0, 0.04);
                border-radius: 4px;
                font-size: 13px;
                color: #50575e;
            }

            .fct_alert_cta {
                margin: 0;
            }
        </style>
        <?php
    }
}
