# Licensing Implementation - FluentCart Integration

**Reference:** https://github.com/WPManageNinja/fluent-plugin-updater-example

Implement licensing using the FluentCart License activation and automatic updater system.

---

## Setup Instructions

### 1. Read Plugin Configuration from CLAUDE.md

**IMPORTANT:** Before implementing licensing, extract these values from the `CLAUDE.md` file in the plugin root:

- **Plugin Name:** Look for "Plugin Name:" in CLAUDE.md
- **Namespace:** Look for "Namespace:" (e.g., `WP_Easy\RoleManager`)
- **Constants Prefix:** Look for "Constants Prefix:" (e.g., `WPE_RM_`)
- **Text Domain:** Look for "Text Domain:" (e.g., `wp-easy-role-manager`)
- **Main Plugin File:** Look for the main plugin file name (usually matches text domain with `.php`)

**Derive from these values:**
- **License Constant Prefix:** Use the Constants Prefix + `LICENSE_` (e.g., `WPE_RM_LICENSE_`)
- **License Settings Key:** Use text domain with underscores + `_license_settings` (e.g., `wpe_rm_license_settings`)
- **License Slug:** Use text domain as-is (e.g., `wpe-role-manager`)
- **Licensing Namespace:** Append `\Licensing` to the main namespace (e.g., `WP_Easy\RoleManager\Licensing`)

---

## Configuration Parameters

**IMPORTANT:** All licensing/updater settings must be defined as global constants in the main plugin file. This centralizes configuration and makes it easy to update values in one location.

### Required Constants (Define in main plugin file)

```php
// Licensing Configuration Constants
define('{PREFIX}LICENSE_ITEM_ID', '[PENDING]'); // Get from FluentCart product page
define('{PREFIX}LICENSE_API_URL', 'https://alanblair.co/');
define('{PREFIX}LICENSE_SLUG', '{text-domain}');
define('{PREFIX}LICENSE_SETTINGS_KEY', '{text_domain_with_underscores}_license_settings');
```

**Replace placeholders:**
- `{PREFIX}` = Constants Prefix from CLAUDE.md + `LICENSE_` (e.g., `WPE_RM_LICENSE_`)
- `{text-domain}` = Text Domain from CLAUDE.md (e.g., `wpe-role-manager`)
- `{text_domain_with_underscores}` = Text Domain with hyphens replaced by underscores (e.g., `wpe_rm`)

### Optional Constants (Define in main plugin file)

```php
// Optional Licensing Settings
define('{PREFIX}LICENSE_MENU_TYPE', 'submenu'); // submenu, options, or menu
define('{PREFIX}LICENSE_MENU_TITLE', 'License');
define('{PREFIX}LICENSE_PAGE_TITLE', '{Plugin Name} - License');
define('{PREFIX}LICENSE_PURCHASE_URL', 'https://wpeasy.au/{slug}/');
define('{PREFIX}LICENSE_ACCOUNT_URL', 'https://alanblair.co/my-account/');

// Development Override (do not document publicly)
define('{PREFIX}LICENSE_DEV_OVERRIDE_KEY', '{slug}-activate-for-dev-20112026');
```

**Replace placeholders:**
- `{PREFIX}` = Constants Prefix + `LICENSE_`
- `{Plugin Name}` = Plugin Name from CLAUDE.md
- `{slug}` = License slug (text domain without hyphens, e.g., `role-manager`)

### Constants Reference

- **{PREFIX}LICENSE_ITEM_ID**: Product ID from FluentCart (required)
- **{PREFIX}LICENSE_API_URL**: WordPress site URL hosting FluentCart (required)
- **{PREFIX}LICENSE_SLUG**: Plugin slug for identification (required)
- **{PREFIX}LICENSE_SETTINGS_KEY**: WordPress option key for storing license data (required)
- **{PREFIX}LICENSE_MENU_TYPE**: Where to add license page (`submenu`, `options`, `menu`)
- **{PREFIX}LICENSE_MENU_TITLE**: Label for license menu item
- **{PREFIX}LICENSE_PAGE_TITLE**: Browser title for license page
- **{PREFIX}LICENSE_PURCHASE_URL**: Link to purchase license
- **{PREFIX}LICENSE_ACCOUNT_URL**: Link to manage existing licenses
- **{PREFIX}LICENSE_DEV_OVERRIDE_KEY**: Development override license key (do not document publicly)

---

## Implementation Checklist

### 1. File Structure (PSR-4 Compliant)

```
/src/Licensing/
  - FluentLicensing.php
  - LicenseSettings.php
  - PluginUpdater.php
```

**Namespace:** `{Namespace}\Licensing` (from CLAUDE.md namespace + `\Licensing`)
**Autoloading:** PSR-4 via composer.json
**No manual requires needed** - classes are autoloaded by composer

### 2. Define Constants (in main plugin file)

Add licensing constants after existing plugin constants:

```php
// Define plugin constants (existing)
define('{PREFIX}VERSION', '1.0.0');
define('{PREFIX}PLUGIN_FILE', __FILE__);
define('{PREFIX}PLUGIN_PATH', plugin_dir_path(__FILE__));
define('{PREFIX}PLUGIN_URL', plugin_dir_url(__FILE__));
define('{PREFIX}TEXTDOMAIN', '{text-domain}');
// ... other existing constants ...

// Licensing Configuration Constants (add these)
define('{PREFIX}LICENSE_ITEM_ID', '[PENDING]'); // TODO: Get from FluentCart
define('{PREFIX}LICENSE_API_URL', 'https://alanblair.co/');
define('{PREFIX}LICENSE_SLUG', '{text-domain}');
define('{PREFIX}LICENSE_SETTINGS_KEY', '{text_domain_with_underscores}_license_settings');
define('{PREFIX}LICENSE_MENU_TYPE', 'submenu');
define('{PREFIX}LICENSE_MENU_TITLE', 'License');
define('{PREFIX}LICENSE_PAGE_TITLE', '{Plugin Name} - License');
define('{PREFIX}LICENSE_PURCHASE_URL', 'https://wpeasy.au/{slug}/');
define('{PREFIX}LICENSE_ACCOUNT_URL', 'https://alanblair.co/my-account/');
define('{PREFIX}LICENSE_DEV_OVERRIDE_KEY', '{slug}-activate-for-dev-20112026');
```

**Replace all placeholders with actual values from CLAUDE.md**

### 3. Initialize Licensing (in main plugin file)

Add licensing initialization using the defined constants (PSR-4 autoloads the classes):

```php
// Initialize licensing on 'init' hook
add_action('init', function() {
    // Check if licensing class exists (PSR-4 autoloaded from src/Licensing/)
    if (!class_exists('{Namespace}\\Licensing\\FluentLicensing')) {
        return; // Licensing files not installed
    }

    $licensing = {Namespace}\Licensing\FluentLicensing::getInstance();
    $licensing->register([
        'version' => {PREFIX}VERSION,
        'item_id' => {PREFIX}LICENSE_ITEM_ID,
        'basename' => plugin_basename(__FILE__),
        'api_url' => {PREFIX}LICENSE_API_URL,
        'slug' => {PREFIX}LICENSE_SLUG,
        'settings_key' => {PREFIX}LICENSE_SETTINGS_KEY,
    ]);

    // Initialize settings page if LicenseSettings class exists
    if (class_exists('{Namespace}\\Licensing\\LicenseSettings')) {
        $licenseSettings = new {Namespace}\Licensing\LicenseSettings();
        $licenseSettings->register($licensing, [
            'menu_title' => {PREFIX}LICENSE_MENU_TITLE,
            'page_title' => {PREFIX}LICENSE_PAGE_TITLE,
            'title' => {PREFIX}LICENSE_PAGE_TITLE,
            'purchase_url' => {PREFIX}LICENSE_PURCHASE_URL,
            'account_url' => {PREFIX}LICENSE_ACCOUNT_URL,
            'plugin_name' => '{Plugin Name}',
        ]);

        // Add the license page as submenu
        $licenseSettings->addPage([
            'type' => {PREFIX}LICENSE_MENU_TYPE,
            'page_title' => {PREFIX}LICENSE_PAGE_TITLE,
            'menu_title' => {PREFIX}LICENSE_MENU_TITLE,
            'parent_slug' => '{main-menu-slug}', // Use the main admin menu slug
            'capability' => 'manage_options',
        ]);
    }
});
```

**Replace placeholders:**
- `{Namespace}` = Full namespace from CLAUDE.md (with backslashes escaped if in string context)
- `{PREFIX}` = Constants Prefix from CLAUDE.md
- `{Plugin Name}` = Plugin Name from CLAUDE.md
- `{main-menu-slug}` = Main admin menu slug (usually text domain)

### 4. Access Control Logic

```php
// Check license status before displaying main plugin
$licensing = {Namespace}\Licensing\FluentLicensing::getInstance();
$status = $licensing->getStatus(); // Local check (fast)

if ($status->status !== 'valid' && !is_dev_override()) {
    // Show license activation page only
    display_license_required_notice();
    return;
}

// Show full plugin interface
```

### 5. Status Checking Strategy

- **On Admin Load**: Use `getStatus()` for fast local verification
- **Daily Cron**: Use `getStatus(true)` for remote server validation
- **Handle Status Values**:
  - `valid` → Full plugin access
  - `invalid` → Show expired notice with renewal link
  - `disabled` → Show refund/disabled message
  - `unregistered` → Show activation form
  - `error` → Show error + allow retry

### 6. Error Handling Pattern

```php
$result = $licensing->activate($license_key);

if (is_wp_error($result)) {
    // Handle error
    $error_message = $result->get_error_message();
    display_error_notice($error_message);
} else {
    // Success
    $license_data = $result;
    display_success_notice();
}
```

---

## Local/Dev Site Exclusions

### Detection Methods

Check for common local/dev indicators:
- Domain: `localhost`, `.local`, `.test`, `.dev`, `.invalid`
- IP addresses: `127.0.0.1`, `::1`, `10.*.*.*`, `192.168.*.*`, `172.16-31.*.*`
- URLs containing: `staging`, `dev`, `development`
- WordPress constants: `WP_LOCAL_DEV === true`

### Implementation

```php
function is_local_dev_site() {
    $host = parse_url(get_site_url(), PHP_HOST);

    // Check TLDs
    $local_tlds = ['.local', '.test', '.dev', '.invalid', '.localhost'];
    foreach ($local_tlds as $tld) {
        if (str_ends_with($host, $tld)) return true;
    }

    // Check IP ranges
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
    }

    // Check keywords
    $dev_keywords = ['localhost', 'staging', 'dev', 'development'];
    foreach ($dev_keywords as $keyword) {
        if (stripos($host, $keyword) !== false) return true;
    }

    // Check WP constant
    if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) return true;

    return false;
}
```

---

## Development Override License

### Override Key

Use the constant: `{PREFIX}LICENSE_DEV_OVERRIDE_KEY`
Default pattern: `{slug}-activate-for-dev-20112026`

### Implementation Requirements

When this license is entered:
1. **Do NOT** call FluentCart API (skip all remote calls)
2. Store override flag: `update_option('{options_prefix}_dev_override', true)`
3. Return mock "valid" status locally
4. Set license status to `valid` immediately
5. Disable all update checks from FluentCart
6. Log override activation for debugging

### Override Check Function

```php
function is_dev_override() {
    return get_option('{options_prefix}_dev_override', false) === true;
}

function check_license_key($key) {
    // Check for development override key (using constant)
    if ($key === {PREFIX}LICENSE_DEV_OVERRIDE_KEY) {
        update_option('{options_prefix}_dev_override', true);
        update_option('{options_prefix}_license_key', $key);
        return [
            'status' => 'valid',
            'expires' => 'never',
            'override' => true
        ];
    }

    // Normal FluentCart activation
    $licensing = {Namespace}\Licensing\FluentLicensing::getInstance();
    return $licensing->activate($key);
}
```

**Replace placeholders:**
- `{PREFIX}` = Constants Prefix from CLAUDE.md
- `{options_prefix}` = Text domain with underscores (e.g., `wpe_rm`)
- `{Namespace}` = Full namespace from CLAUDE.md

### Security Note

This override should:
- Be clearly marked as "Development Override" in UI
- Show warning that updates are disabled
- Not be documented in public-facing documentation
- Optionally expire after specific date (2026-11-20)

---

## UI/UX Requirements

### License Not Active State

- **Hide:** All main plugin tabs and functionality
- **Show:**
  - Clean license activation page
  - Benefits of activation
  - Purchase link
  - "Already have a license?" activation form
  - Support link

### License Active State

- **Show:** Small "Licensed to: [email/name]" indicator in admin footer or settings
- **Show:** "Manage License" link in plugin settings
- **Allow:** Full plugin functionality

### License Expired/Disabled State

- **Show:** Prominent renewal notice
- **Show:** Days until features are disabled (grace period: 7 days)
- **Allow:** Read-only access to existing configurations
- **Block:** Creating new functionality or making changes

---

## Testing Checklist

- [ ] Get item_id from FluentCart product page
- [ ] Test license activation with valid key
- [ ] Test license activation with invalid key
- [ ] Test license deactivation
- [ ] Test local/dev site bypass (all detection methods)
- [ ] Test dev override key
- [ ] Test expired license scenario
- [ ] Test disabled/refunded license scenario
- [ ] Test network/API error scenarios
- [ ] Test automatic updates with active license
- [ ] Verify license status caching and refresh timing
- [ ] Test settings page UI/UX
- [ ] Verify admin access blocking when unlicensed

---

## Critical Action Items

1. **Read CLAUDE.md:** Extract namespace, prefix, text domain, and plugin name
2. **Define Constants:** Add all `{PREFIX}LICENSE_*` constants to main plugin file after existing constants
3. **Get Item ID:** Contact FluentCart/create product to obtain item ID, then update `{PREFIX}LICENSE_ITEM_ID` constant
4. **Update Namespace:** Ensure licensing classes use `{Namespace}\Licensing` namespace
5. **Configure API URL:** Verify FluentCart installation URL in `{PREFIX}LICENSE_API_URL` constant
6. **Test Override:** Ensure dev override works without API calls
7. **Initialize Licensing:** Add licensing initialization code using the defined constants
8. **Add Status Indicator:** Show license status in plugin admin area
9. **Error Handling:** Implement comprehensive WP_Error handling for all licensing methods
10. **Daily Cron:** Set up `wp_schedule_event` for daily remote license validation

---

## Notes

- **Use constants everywhere:** All licensing configuration must use the `{PREFIX}LICENSE_*` constants defined in the main plugin file. Never hardcode values.
- **Extract from CLAUDE.md first:** Always read plugin configuration from CLAUDE.md before implementing
- License checks should be **fast** - use local `getStatus()` for regular checks
- Remote validation via `getStatus(true)` should run via cron, not on every page load
- Always use `is_wp_error()` before processing results from activate/deactivate/getStatus
- Store license data in WordPress options, not in class properties
- Use singleton pattern: `FluentLicensing::getInstance()` for access throughout plugin
- **Centralized configuration:** All licensing settings are in one place (main plugin file), making updates and maintenance simple

---

## Quick Reference: Placeholder Mapping

| Placeholder | Source | Example |
|------------|--------|---------|
| `{Plugin Name}` | CLAUDE.md "Plugin Name:" | `WP Easy Role Manager` |
| `{Namespace}` | CLAUDE.md "Namespace:" | `WP_Easy\RoleManager` |
| `{PREFIX}` | CLAUDE.md "Constants Prefix:" | `WPE_RM_` |
| `{text-domain}` | CLAUDE.md "Text Domain:" | `wp-easy-role-manager` |
| `{text_domain_with_underscores}` | Text domain with `-` → `_` | `wpe_rm` |
| `{slug}` | Derived from text domain | `role-manager` |
| `{options_prefix}` | Same as text_domain_with_underscores | `wpe_rm` |
| `{main-menu-slug}` | Main admin menu slug | `wpe-role-manager` |
| `{Licensing Namespace}` | Namespace + `\Licensing` | `WP_Easy\RoleManager\Licensing` |
