<?php
/**
 * Settings field schema.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Settings;

defined('ABSPATH') || exit;

/**
 * Declarative definition of the destination connection settings.
 *
 * One source of truth for: defaults, types/sanitisation, which fields are
 * secret (encrypted, never returned to JS), and which wp-config constant
 * overrides each field at read time.
 */
final class Schema {

    /**
     * Option key holding the settings array.
     */
    public const OPTION = 'bs_settings';

    /**
     * Field definitions keyed by setting name.
     *
     * @return array<string,array{type:string,default:mixed,constant:string,secret?:bool,options?:array<int,string>}>
     */
    public static function fields(): array {
        return [
            'transport'      => ['type' => 'enum',   'default' => 'ftps',          'constant' => 'BS_TRANSPORT',       'options' => ['sftp', 'ftps', 'ftp']],
            'host'           => ['type' => 'string', 'default' => '',              'constant' => 'BS_HOST'],
            'port'           => ['type' => 'int',    'default' => 0,               'constant' => 'BS_PORT'],
            'username'       => ['type' => 'string', 'default' => '',              'constant' => 'BS_USERNAME'],
            'password'       => ['type' => 'string', 'default' => '',              'constant' => 'BS_PASSWORD', 'secret' => true],
            'remotePath'     => ['type' => 'path',   'default' => '',              'constant' => 'BS_REMOTE_PATH'],
            'basePath'       => ['type' => 'path',   'default' => '/',             'constant' => 'BS_BASE_PATH'],
            'destinationUrl' => ['type' => 'url',    'default' => '',              'constant' => 'BS_DESTINATION_URL'],
        ];
    }

    /**
     * Whether a field stores a secret (encrypted; never echoed to JS).
     *
     * @param string $key Field key.
     */
    public static function is_secret(string $key): bool {
        return !empty(self::fields()[$key]['secret']);
    }

    /**
     * Sanitise a raw value according to its field type.
     *
     * @param string $key   Field key.
     * @param mixed  $value Raw value.
     * @return mixed Sanitised value.
     */
    public static function sanitize(string $key, $value) {
        $field = self::fields()[$key] ?? null;
        if ($field === null) {
            return null;
        }

        switch ($field['type']) {
            case 'int':
                return max(0, (int) $value);

            case 'enum':
                $value = is_string($value) ? $value : '';
                return in_array($value, $field['options'] ?? [], true) ? $value : $field['default'];

            case 'url':
                return esc_url_raw(trim((string) $value));

            case 'path':
                return self::sanitize_path((string) $value);

            case 'string':
            default:
                return sanitize_text_field((string) $value);
        }
    }

    /**
     * Normalise a remote/base path: trim, strip dangerous traversal, ensure a
     * single trailing slash.
     *
     * @param string $value Raw path.
     */
    private static function sanitize_path(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace('\\', '/', $value);
        $value = preg_replace('#\.\.+/#', '', $value) ?? '';
        $value = preg_replace('#/{2,}#', '/', $value) ?? '';

        return trailingslashit($value);
    }
}
