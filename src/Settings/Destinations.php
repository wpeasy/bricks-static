<?php
/**
 * The list of sync destinations + migration from the single-destination model.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Settings;

defined('ABSPATH') || exit;

/**
 * Manages the destinations list (stored in one option) and migrates the legacy
 * single `bs_settings` destination into `destinations[0]` on first run.
 */
final class Destinations {

    /**
     * Option holding the destinations list.
     */
    public const OPTION = 'bs_destinations';

    /**
     * Legacy single-destination option (migrated then left in place).
     */
    private const LEGACY_OPTION = 'bs_settings';

    /**
     * All destination arrays (raw, password encrypted).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array {
        self::ensure_migrated();
        $list = get_option(self::OPTION, []);

        return is_array($list) ? array_values($list) : [];
    }

    /**
     * All destinations as objects (index 0 is primary).
     *
     * @return array<int,Destination>
     */
    public static function objects(): array {
        $out = [];
        foreach (self::all() as $i => $data) {
            $out[] = new Destination($data, $i === 0);
        }

        return $out;
    }

    /**
     * The primary destination (always exists after migration).
     */
    public static function primary(): Destination {
        $objects = self::objects();

        return $objects[0] ?? new Destination(self::blank(self::new_id(), 'Destination 1'), true);
    }

    /**
     * Ids of all enabled destinations (used by "sync all").
     *
     * @return array<int,string>
     */
    public static function enabled_ids(): array {
        $ids = [];
        foreach (self::objects() as $dest) {
            if ((bool) $dest->get('enabled')) {
                $ids[] = $dest->id();
            }
        }

        return $ids;
    }

    /**
     * Find a destination by id.
     *
     * @param string $id Destination id.
     */
    public static function get(string $id): ?Destination {
        foreach (self::objects() as $dest) {
            if ($dest->id() === $id) {
                return $dest;
            }
        }

        return null;
    }

    /**
     * Display payload for all destinations.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function for_display(): array {
        return array_map(static fn(Destination $d): array => $d->for_display(), self::objects());
    }

    /**
     * Add a new destination.
     *
     * @param array<string,mixed> $input Initial values.
     */
    public static function add(array $input): Destination {
        $list  = self::all();
        $count = count($list) + 1;
        $dest  = new Destination(self::blank(self::new_id(), 'Destination ' . $count), false);

        $list[] = $dest->apply($input);
        update_option(self::OPTION, $list, false);

        return new Destination($list[array_key_last($list)], false);
    }

    /**
     * Update a destination by id.
     *
     * @param string              $id    Destination id.
     * @param array<string,mixed> $input New values.
     */
    public static function update(string $id, array $input): ?Destination {
        $list = self::all();

        foreach ($list as $i => $data) {
            if ((string) ($data['id'] ?? '') === $id) {
                $before  = self::target_signature(new Destination($data, $i === 0));
                $dest    = new Destination($data, $i === 0);
                $list[$i] = $dest->apply($input);
                update_option(self::OPTION, $list, false);

                $after = new Destination($list[$i], $i === 0);

                // If the destination now points at a different server/location,
                // its push record described the OLD target and is no longer valid
                // — drop it so the next sync uploads everything afresh.
                if (self::target_signature($after) !== $before) {
                    delete_option(self::pushed_option($id));
                }

                return $after;
            }
        }

        return null;
    }

    /**
     * A fingerprint of WHERE a destination uploads to (not the password). When
     * this changes, any recorded push manifest no longer reflects the remote.
     */
    private static function target_signature(Destination $d): string {
        $c = $d->connection_config();

        return implode('|', [
            (string) ($c['transport'] ?? ''),
            strtolower((string) ($c['host'] ?? '')),
            (string) ($c['port'] ?? ''),
            (string) ($c['username'] ?? ''),
            trim((string) ($c['remotePath'] ?? ''), '/'),
        ]);
    }

    /**
     * Remove a destination (and its pushed manifest). The primary cannot be
     * removed while it is the only destination.
     *
     * @param string $id Destination id.
     */
    public static function remove(string $id): bool {
        $list = self::all();
        if (count($list) <= 1) {
            return false;
        }

        $kept = array_values(array_filter($list, static fn(array $d): bool => (string) ($d['id'] ?? '') !== $id));
        if (count($kept) === count($list)) {
            return false;
        }

        update_option(self::OPTION, $kept, false);
        delete_option(self::pushed_option($id));

        return true;
    }

    /**
     * Option name holding a destination's pushed manifest.
     *
     * @param string $id Destination id.
     */
    public static function pushed_option(string $id): string {
        return 'bs_pushed_' . $id;
    }

    /**
     * Ensure the destinations list exists, migrating the legacy option once.
     */
    public static function ensure_migrated(): void {
        $existing = get_option(self::OPTION, null);
        if (is_array($existing) && !empty($existing)) {
            return;
        }

        $id   = self::new_id();
        $dest = self::blank($id, 'Destination 1');

        $legacy = get_option(self::LEGACY_OPTION, null);
        if (is_array($legacy)) {
            foreach (Schema::fields() as $key => $field) {
                if (array_key_exists($key, $legacy)) {
                    $dest[$key] = $legacy[$key];
                }
            }
        }

        update_option(self::OPTION, [$dest], false);

        // Carry over the single pushed manifest to the migrated destination.
        $pushed = get_option('bs_pushed_manifest', null);
        if (is_array($pushed)) {
            update_option(self::pushed_option($id), $pushed, false);
        }
    }

    /**
     * A blank destination seeded with field defaults.
     *
     * @param string $id   Destination id.
     * @param string $name Destination name.
     * @return array<string,mixed>
     */
    private static function blank(string $id, string $name): array {
        $data = [
            'id'                      => $id,
            'name'                    => $name,
            'enabled'                 => true,
            'includeInSinglePageSync' => true,
            'replacements'            => [],
        ];

        foreach (Schema::fields() as $key => $field) {
            $data[$key] = $field['default'];
        }

        return $data;
    }

    /**
     * Generate a short, unique destination id.
     */
    public static function new_id(): string {
        return substr(str_replace('-', '', wp_generate_uuid4()), 0, 12);
    }
}
