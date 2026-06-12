<?php
/**
 * Settings read/write.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Settings;

use WPEasy\BricksStatic\Support\Crypto;

defined('ABSPATH') || exit;

/**
 * Typed access to the destination connection settings.
 *
 * Read precedence per field: a defined wp-config constant wins over the stored
 * DB value. Secret fields are stored encrypted and are never included in the
 * display payload sent to the browser.
 */
final class Settings {

    /**
     * Effective value of a single field (constant override, else stored).
     *
     * For secret fields this returns the decrypted plaintext — server-side only.
     *
     * @param string $key Field key.
     * @return mixed
     */
    public static function get(string $key) {
        $fields = Schema::fields();
        if (!isset($fields[$key])) {
            return null;
        }

        $constant = $fields[$key]['constant'];
        if ($constant !== '' && defined($constant)) {
            return constant($constant);
        }

        $stored = self::stored();
        $raw    = $stored[$key] ?? $fields[$key]['default'];

        if (Schema::is_secret($key)) {
            return is_string($raw) && Crypto::is_encrypted($raw) ? Crypto::decrypt($raw) : (string) $raw;
        }

        return $raw;
    }

    /**
     * Whether a field's value is currently supplied by a wp-config constant.
     *
     * @param string $key Field key.
     */
    public static function is_from_constant(string $key): bool {
        $field = Schema::fields()[$key] ?? null;

        return $field !== null && $field['constant'] !== '' && defined($field['constant']);
    }

    /**
     * Effective config for the transport layer (decrypted password included).
     *
     * @return array<string,mixed>
     */
    public static function connection_config(): array {
        return [
            'transport'  => (string) self::get('transport'),
            'host'       => (string) self::get('host'),
            'port'       => (int) self::get('port'),
            'username'   => (string) self::get('username'),
            'password'   => (string) self::get('password'),
            'remotePath' => (string) self::get('remotePath'),
        ];
    }

    /**
     * Non-secret settings for the dashboard, with per-field constant flags.
     *
     * The password is never returned — only whether one is set and whether it
     * comes from a constant.
     *
     * @return array<string,mixed>
     */
    public static function for_display(): array {
        $out = [];

        foreach (Schema::fields() as $key => $field) {
            if (Schema::is_secret($key)) {
                $out[$key] = [
                    'hasValue'     => self::get($key) !== '',
                    'fromConstant' => self::is_from_constant($key),
                ];
                continue;
            }

            $out[$key] = [
                'value'        => self::get($key),
                'fromConstant' => self::is_from_constant($key),
            ];
        }

        return $out;
    }

    /**
     * Validate, sanitise and persist a settings payload.
     *
     * Constant-overridden fields are ignored. An empty password is treated as
     * "leave unchanged"; pass clearPassword=true to remove it.
     *
     * @param array<string,mixed> $input Raw input from the REST request.
     * @return array<string,mixed> The new display payload.
     */
    public static function save(array $input): array {
        $stored = self::stored();

        foreach (Schema::fields() as $key => $field) {
            if (self::is_from_constant($key)) {
                continue; // Constant wins; don't shadow it in the DB.
            }

            if (Schema::is_secret($key)) {
                $clear = !empty($input['clearPassword']);
                if ($clear) {
                    $stored[$key] = '';
                } elseif (isset($input[$key]) && (string) $input[$key] !== '') {
                    $stored[$key] = Crypto::encrypt((string) $input[$key]);
                }
                continue;
            }

            if (array_key_exists($key, $input)) {
                $stored[$key] = Schema::sanitize($key, $input[$key]);
            }
        }

        update_option(Schema::OPTION, $stored, false);

        return self::for_display();
    }

    /**
     * Raw stored option array.
     *
     * @return array<string,mixed>
     */
    private static function stored(): array {
        $value = get_option(Schema::OPTION, []);

        return is_array($value) ? $value : [];
    }
}
