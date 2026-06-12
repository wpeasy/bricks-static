<?php
/**
 * WP-CLI detection + command building across environments.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.1
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\System;

use WPEasy\BricksStatic\Support\Environment;

defined('ABSPATH') || exit;

/**
 * Detects WP-CLI and produces fully-formed shell commands that work across
 * environments:
 *
 *  - System install (`wp` on $PATH) — used as-is.
 *  - Local by Flywheel — Local bundles WP-CLI inside its app install and does
 *    NOT put `wp` on the PHP PATH, so a naive `wp --version` reports "not
 *    available" even when WP-CLI is fully functional. For Local we probe the
 *    bundled php.exe + wp-cli.phar pair, and resolve the SITE's rendered
 *    php.ini so DB-touching commands inherit the right mysqli.default_port.
 *
 * Result is cached in a transient; re-detect with WpCli::refresh().
 */
final class WpCli {

    public const TRANSIENT_KEY = 'bs_wpcli_status';
    public const CACHE_TTL     = HOUR_IN_SECONDS;

    /** Cache schema version — bump when the cached array shape changes. */
    private const CACHE_SCHEMA = 1;

    public const RUNTIME_SYSTEM = 'system';
    public const RUNTIME_LOCAL  = 'local';

    /**
     * @return array{available:bool,version:?string,reason:?string,runtime:?array}
     */
    public static function status(): array {
        $cached = get_transient(self::TRANSIENT_KEY);
        if (is_array($cached)
            && ($cached['_schema'] ?? null) === self::CACHE_SCHEMA
            && array_key_exists('available', $cached)
        ) {
            return $cached;
        }

        return self::refresh();
    }

    /**
     * @return array{available:bool,version:?string,reason:?string,runtime:?array}
     */
    public static function refresh(): array {
        $status            = self::detect();
        $status['_schema'] = self::CACHE_SCHEMA;
        set_transient(self::TRANSIENT_KEY, $status, self::CACHE_TTL);

        return $status;
    }

    /**
     * Build a shell command for `wp <args>` using the detected runtime, or null.
     *
     * @param string $args WP-CLI arguments (already escaped where needed).
     */
    public static function build_command(string $args): ?string {
        $status = self::status();
        if (empty($status['available']) || !is_array($status['runtime'] ?? null)) {
            return null;
        }

        $runtime = $status['runtime'];
        $type    = (string) ($runtime['type'] ?? '');

        if ($type === self::RUNTIME_SYSTEM) {
            return 'wp ' . $args;
        }

        if ($type === self::RUNTIME_LOCAL) {
            $php  = (string) ($runtime['php'] ?? '');
            $phar = (string) ($runtime['phar'] ?? '');
            $ini  = isset($runtime['ini']) && is_string($runtime['ini']) ? $runtime['ini'] : '';
            if ($php === '' || $phar === '') {
                return null;
            }

            $parts = [escapeshellarg($php)];
            if ($ini !== '' && is_file($ini)) {
                $parts[] = '-c';
                $parts[] = escapeshellarg($ini);
            }
            $parts[] = escapeshellarg($phar);
            $parts[] = $args;

            return implode(' ', $parts);
        }

        return null;
    }

    /**
     * @return array{available:bool,version:?string,reason:?string,runtime:?array}
     */
    private static function detect(): array {
        if (!function_exists('proc_open') || !function_exists('proc_close')) {
            return ['available' => false, 'version' => null, 'reason' => 'proc_open() is disabled on this host.', 'runtime' => null];
        }

        // On Local, `wp` is on PATH only in the CLI, not the web context that
        // does the spawning — so prefer the bundled runtime there, which works
        // in both. Elsewhere, a system `wp` is the natural first choice.
        $prefer_local = Environment::is_local();

        if ($prefer_local) {
            $local = self::probe_local();
            if ($local !== null) {
                return ['available' => true, 'version' => $local['version'], 'reason' => null, 'runtime' => $local['runtime']];
            }
        }

        $sys = self::probe_system();
        if ($sys !== null) {
            return ['available' => true, 'version' => $sys['version'], 'reason' => null, 'runtime' => $sys['runtime']];
        }

        if (!$prefer_local) {
            $local = self::probe_local();
            if ($local !== null) {
                return ['available' => true, 'version' => $local['version'], 'reason' => null, 'runtime' => $local['runtime']];
            }
        }

        return ['available' => false, 'version' => null, 'reason' => 'No `wp` on PATH and no Local-bundled WP-CLI found.', 'runtime' => null];
    }

    /**
     * @return array{version:?string,runtime:array{type:string}}|null
     */
    private static function probe_system(): ?array {
        $result = self::try_command('wp --version');
        if (!$result['ok']) {
            return null;
        }

        return ['version' => self::parse_version($result['stdout']), 'runtime' => ['type' => self::RUNTIME_SYSTEM]];
    }

    /**
     * @return array{version:?string,runtime:array{type:string,php:string,phar:string,ini:?string}}|null
     */
    private static function probe_local(): ?array {
        foreach (self::find_local_runtime_pairs() as $pair) {
            [$php, $phar] = $pair;
            $result = self::try_command(escapeshellarg($php) . ' ' . escapeshellarg($phar) . ' --version');
            if (!$result['ok']) {
                continue;
            }

            return [
                'version' => self::parse_version($result['stdout']),
                'runtime' => [
                    'type' => self::RUNTIME_LOCAL,
                    'php'  => $php,
                    'phar' => $phar,
                    'ini'  => self::resolve_local_site_ini(),
                ],
            ];
        }

        return null;
    }

    /**
     * @return array<int,array{0:string,1:string}>
     */
    private static function find_local_runtime_pairs(): array {
        $roots = [
            'C:\\Program Files (x86)\\Local\\resources\\extraResources',
            'C:\\Program Files\\Local\\resources\\extraResources',
        ];

        $pairs = [];
        foreach ($roots as $root) {
            $phar = $root . '\\bin\\wp-cli\\wp-cli.phar';
            if (!is_file($phar)) {
                continue;
            }

            $phps = glob($root . '\\lightning-services\\php-*\\bin\\win64\\php.exe') ?: [];
            if (!$phps) {
                continue;
            }

            usort($phps, static function (string $a, string $b): int {
                preg_match('/php-([0-9.]+)/', $a, $am);
                preg_match('/php-([0-9.]+)/', $b, $bm);
                return -version_compare((string) ($am[1] ?? '0'), (string) ($bm[1] ?? '0'));
            });

            $pairs[] = [$phps[0], $phar];
        }

        return $pairs;
    }

    /**
     * Resolve the current Local site's rendered php.ini (carries the per-site
     * mysqli.default_port override DB commands need).
     */
    private static function resolve_local_site_ini(): ?string {
        $abs = self::normalize_path((string) ABSPATH);
        if (!preg_match('|/Local Sites/([^/]+)/app/public|', $abs, $m)) {
            return null;
        }
        $site_folder = $m[1];

        $home = self::user_home();
        if ($home === null) {
            return null;
        }

        $sites_json = $home . '/AppData/Roaming/Local/sites.json';
        if (!is_file($sites_json)) {
            return null;
        }

        $sites = json_decode((string) @file_get_contents($sites_json), true);
        if (!is_array($sites)) {
            return null;
        }

        $needle = '/' . $site_folder;
        foreach ($sites as $site_id => $site) {
            if (!is_array($site)) {
                continue;
            }
            $site_path = self::normalize_path((string) ($site['path'] ?? ''));
            if ($site_path === '' || !str_ends_with($site_path, $needle)) {
                continue;
            }
            $ini = $home . '/AppData/Roaming/Local/run/' . $site_id . '/conf/php/php.ini';
            if (is_file($ini)) {
                return $ini;
            }
        }

        return null;
    }

    private static function user_home(): ?string {
        $home = $_SERVER['USERPROFILE'] ?? getenv('USERPROFILE');
        if (!$home) {
            $drive = $_SERVER['HOMEDRIVE'] ?? getenv('HOMEDRIVE');
            $path  = $_SERVER['HOMEPATH'] ?? getenv('HOMEPATH');
            if ($drive && $path) {
                $home = $drive . $path;
            }
        }

        return is_string($home) && $home !== '' ? self::normalize_path($home) : null;
    }

    private static function normalize_path(string $path): string {
        $path = str_replace('\\', '/', $path);

        return (string) preg_replace('|/+|', '/', $path);
    }

    private static function parse_version(string $stdout): ?string {
        return preg_match('/WP-CLI\s+(\S+)/i', $stdout, $m) ? $m[1] : null;
    }

    /**
     * @return array{ok:bool,stdout:string,stderr:string,exit:int}
     */
    private static function try_command(string $cmd): array {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['ok' => false, 'stdout' => '', 'stderr' => 'spawn failed', 'exit' => -1];
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['ok' => $exit === 0, 'stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }
}
