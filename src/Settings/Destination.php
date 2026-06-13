<?php
/**
 * A single sync destination (connection + meta + text replacements).
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Settings;

use WPEasy\BricksStatic\Support\Crypto;

defined('ABSPATH') || exit;

/**
 * Wraps one destination's stored array and applies the connection-field rules
 * (wp-config constant overrides apply to the primary destination only; secret
 * fields are encrypted and never echoed to the browser).
 */
final class Destination {

    /**
     * Stored destination data.
     *
     * @var array<string,mixed>
     */
    private array $data;

    /**
     * Whether this is the primary destination (constants apply to it).
     */
    private bool $is_primary;

    /**
     * @param array<string,mixed> $data       Stored destination array.
     * @param bool                $is_primary Whether constants apply.
     */
    public function __construct(array $data, bool $is_primary = false) {
        $this->data       = $data;
        $this->is_primary = $is_primary;
    }

    /**
     * Destination id.
     */
    public function id(): string {
        return (string) ($this->data['id'] ?? '');
    }

    /**
     * The raw stored array (password stays encrypted).
     *
     * @return array<string,mixed>
     */
    public function raw(): array {
        return $this->data;
    }

    /**
     * Effective value of a connection field (constant override on primary, else
     * stored; secrets decrypted) or a meta field.
     *
     * @param string $key Field key.
     * @return mixed
     */
    public function get(string $key) {
        $fields = Schema::fields();

        if (!isset($fields[$key])) {
            return $this->data[$key] ?? null;
        }

        if ($this->is_from_constant($key)) {
            return constant($fields[$key]['constant']);
        }

        $raw = $this->data[$key] ?? $fields[$key]['default'];

        if (Schema::is_secret($key)) {
            return is_string($raw) && Crypto::is_encrypted($raw) ? Crypto::decrypt($raw) : (string) $raw;
        }

        return $raw;
    }

    /**
     * Whether a connection field is supplied by a wp-config constant.
     *
     * @param string $key Field key.
     */
    public function is_from_constant(string $key): bool {
        $field = Schema::fields()[$key] ?? null;

        return $this->is_primary && $field !== null && $field['constant'] !== '' && defined($field['constant']);
    }

    /**
     * Connection config for the transport layer (decrypted password included).
     *
     * @return array<string,mixed>
     */
    public function connection_config(): array {
        return [
            'transport'  => (string) $this->get('transport'),
            'host'       => (string) $this->get('host'),
            'port'       => (int) $this->get('port'),
            'username'   => (string) $this->get('username'),
            'password'   => (string) $this->get('password'),
            'remotePath' => (string) $this->get('remotePath'),
        ];
    }

    /**
     * Per-destination text replacements [{search, replace}, …].
     *
     * @return array<int,array{search:string,replace:string}>
     */
    public function replacements(): array {
        $out = [];
        foreach ((array) ($this->data['replacements'] ?? []) as $row) {
            if (is_array($row) && isset($row['search']) && $row['search'] !== '') {
                $out[] = [
                    'search'  => (string) $row['search'],
                    'replace' => (string) ($row['replace'] ?? ''),
                    'rich'    => (bool) ($row['rich'] ?? false),
                ];
            }
        }

        return $out;
    }

    /**
     * Per-destination media swaps [{from, to}, …] — original media URL → its
     * replacement (a WordPress media-library URL).
     *
     * @return array<int,array{from:string,to:string}>
     */
    public function media_replacements(): array {
        $out = [];
        foreach ((array) ($this->data['mediaReplacements'] ?? []) as $row) {
            if (is_array($row) && !empty($row['from']) && !empty($row['to'])) {
                $out[] = ['from' => (string) $row['from'], 'to' => (string) $row['to']];
            }
        }

        return $out;
    }

    /**
     * Display payload (no secrets) for the dashboard.
     *
     * @return array<string,mixed>
     */
    public function for_display(): array {
        $out = [
            'id'                      => $this->id(),
            'name'                    => (string) ($this->data['name'] ?? ''),
            'enabled'                 => (bool) ($this->data['enabled'] ?? true),
            'includeInSinglePageSync' => (bool) ($this->data['includeInSinglePageSync'] ?? true),
            'isPrimary'               => $this->is_primary,
            'replacements'            => $this->replacements(),
            'mediaReplacements'       => $this->media_replacements(),
        ];

        foreach (Schema::fields() as $key => $field) {
            if (Schema::is_secret($key)) {
                $out[$key] = ['hasValue' => $this->get($key) !== '', 'fromConstant' => $this->is_from_constant($key)];
                continue;
            }
            $out[$key] = ['value' => $this->get($key), 'fromConstant' => $this->is_from_constant($key)];
        }

        return $out;
    }

    /**
     * Apply a settings payload, returning the updated stored array.
     *
     * @param array<string,mixed> $input Raw input.
     * @return array<string,mixed> Updated stored data.
     */
    public function apply(array $input): array {
        // Meta.
        if (array_key_exists('name', $input)) {
            $this->data['name'] = sanitize_text_field((string) $input['name']);
        }
        if (array_key_exists('enabled', $input)) {
            $this->data['enabled'] = (bool) $input['enabled'];
        }
        if (array_key_exists('includeInSinglePageSync', $input)) {
            $this->data['includeInSinglePageSync'] = (bool) $input['includeInSinglePageSync'];
        }
        if (array_key_exists('replacements', $input) && is_array($input['replacements'])) {
            $this->data['replacements'] = self::sanitize_replacements($input['replacements']);
        }
        if (array_key_exists('mediaReplacements', $input) && is_array($input['mediaReplacements'])) {
            $this->data['mediaReplacements'] = self::sanitize_media_replacements($input['mediaReplacements']);
        }

        // Connection fields.
        foreach (Schema::fields() as $key => $field) {
            if ($this->is_from_constant($key)) {
                continue;
            }

            if (Schema::is_secret($key)) {
                if (!empty($input['clearPassword'])) {
                    $this->data[$key] = '';
                } elseif (isset($input[$key]) && (string) $input[$key] !== '') {
                    $this->data[$key] = Crypto::encrypt((string) $input[$key]);
                }
                continue;
            }

            if (array_key_exists($key, $input)) {
                $this->data[$key] = Schema::sanitize($key, $input[$key]);
            }
        }

        return $this->data;
    }

    /**
     * Sanitise a replacements list (literal search/replace; empty search dropped).
     *
     * @param array<int,mixed> $rows Raw rows.
     * @return array<int,array{search:string,replace:string}>
     */
    private static function sanitize_replacements(array $rows): array {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $search = sanitize_text_field((string) ($row['search'] ?? ''));
            if ($search === '') {
                continue;
            }
            // Rich replacements may carry safe inline HTML; plain ones are text.
            $rich    = !empty($row['rich']);
            $replace = (string) ($row['replace'] ?? '');
            $replace = $rich ? wp_kses_post($replace) : sanitize_text_field($replace);
            $clean[] = ['search' => $search, 'replace' => $replace, 'rich' => $rich];
        }

        return $clean;
    }

    /**
     * Sanitise a media-replacement list (both sides are URLs; empties dropped).
     *
     * @param array<int,mixed> $rows Raw rows.
     * @return array<int,array{from:string,to:string}>
     */
    private static function sanitize_media_replacements(array $rows): array {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $from = esc_url_raw((string) ($row['from'] ?? ''));
            $to   = esc_url_raw((string) ($row['to'] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }
            $clean[] = ['from' => $from, 'to' => $to];
        }

        return $clean;
    }
}
