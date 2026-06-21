<?php
/**
 * Deploy-pipeline registry — the seam between the Runner and pluggable
 * replacers / extra-file generators.
 *
 * Free registers its Text replacer here; the Pro addon registers the media,
 * link, video and data-attribute replacers plus the sitemap/robots emitter.
 * The Runner consults this registry at deploy time, so it never references a
 * concrete (or Pro) replacer class directly.
 *
 * @package WPEasy\BricksStatic
 * @since   0.0.4
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Sync;

defined('ABSPATH') || exit;

/**
 * Static registry of deploy-time replacers and extra-file emitters.
 *
 * Order is insertion order; the deploy signature sorts by replacer key so it is
 * independent of registration order (see Runner::sync_signature).
 */
final class Pipeline {

    /**
     * Registered replacers, in registration order.
     *
     * @var array<int,DeployReplacer>
     */
    private static array $replacers = [];

    /**
     * Registered extra-file emitters. Each is callable($pageUrls): array<string,string>
     * returning relativePath => contents (e.g. sitemap.xml, robots.txt).
     *
     * @var array<int,callable>
     */
    private static array $emitters = [];

    /**
     * Register a deploy-time replacer.
     *
     * @param DeployReplacer $replacer Replacer instance.
     */
    public static function register_replacer(DeployReplacer $replacer): void {
        self::$replacers[] = $replacer;
    }

    /**
     * Register an extra-file emitter (sitemap/robots, etc.).
     *
     * @param callable $emit callable(array<int,string> $pageUrls): array<string,string>
     */
    public static function register_extra_files(callable $emit): void {
        self::$emitters[] = $emit;
    }

    /**
     * All registered replacers, in registration order.
     *
     * @return array<int,DeployReplacer>
     */
    public static function replacers(): array {
        return self::$replacers;
    }

    /**
     * All registered extra-file emitters.
     *
     * @return array<int,callable>
     */
    public static function extra_file_emitters(): array {
        return self::$emitters;
    }

    /**
     * Clear the registry (test/CLI re-entrancy helper).
     */
    public static function reset(): void {
        self::$replacers = [];
        self::$emitters  = [];
    }
}
