<?php
/**
 * Settings read/write — compatibility shim over the primary destination.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Settings;

defined('ABSPATH') || exit;

/**
 * Single-destination accessors that now delegate to the primary destination, so
 * existing callers (transport, REST, status) keep working while the data model
 * is multi-destination underneath.
 */
final class Settings {

    /**
     * Effective value of a primary-destination field.
     *
     * @param string $key Field key.
     * @return mixed
     */
    public static function get(string $key) {
        return Destinations::primary()->get($key);
    }

    /**
     * Whether a primary-destination field comes from a wp-config constant.
     *
     * @param string $key Field key.
     */
    public static function is_from_constant(string $key): bool {
        return Destinations::primary()->is_from_constant($key);
    }

    /**
     * Connection config for the primary destination's transport.
     *
     * @return array<string,mixed>
     */
    public static function connection_config(): array {
        return Destinations::primary()->connection_config();
    }

    /**
     * Display payload for the primary destination.
     *
     * @return array<string,mixed>
     */
    public static function for_display(): array {
        return Destinations::primary()->for_display();
    }

    /**
     * Persist a payload to the primary destination.
     *
     * @param array<string,mixed> $input Raw input.
     * @return array<string,mixed> The new display payload.
     */
    public static function save(array $input): array {
        $primary = Destinations::primary();
        Destinations::update($primary->id(), $input);

        return Destinations::primary()->for_display();
    }
}
