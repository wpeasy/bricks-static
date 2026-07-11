<?php
/**
 * Translatable UI strings for the Pro replacement panels.
 *
 * Mirrors Free's `Support\I18n`, but for the Pro addon: `Admin\ProMenu` localises
 * `self::all()` onto `window.bspData.i18n`, which the Free `shared/i18n.ts` helper
 * merges over `window.bsData.i18n`. Domain: `bricks-static-pro`.
 *
 * @package WPEasy\BricksStaticPro
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStaticPro\Support;

defined('ABSPATH') || exit;

/**
 * The Pro panels' i18n dictionary (key => translated string).
 */
final class I18n {

    /**
     * The full key => string map handed to the Pro JS bundle.
     *
     * @return array<string,string>
     */
    public static function all(): array {
        return array_merge(
            self::common(),
            self::media(),
            self::links(),
            self::videos()
        );
    }

    /**
     * Strings shared across the Pro panels.
     *
     * @return array<string,string>
     */
    private static function common(): array {
        return [
            'mediaUnavailable' => __('The WordPress media library is unavailable on this page.', 'bricks-static-pro'),
            'btnReplace'       => __('Replace…', 'bricks-static-pro'),
            'btnRemove'        => __('Remove', 'bricks-static-pro'),
            'filterByPage'     => __('Filter by page…', 'bricks-static-pro'),
            'filterByPageAria' => __('Filter by page', 'bricks-static-pro'),
            'onlyReplacements' => __('Only replacements', 'bricks-static-pro'),
        ];
    }

    /**
     * Media replacer.
     *
     * @return array<string,string>
     */
    private static function media(): array {
        return [
            'mediaReplacer'      => __('Image Replacer', 'bricks-static-pro'),
            /* translators: %d is an item count (singular). */
            'nItem'              => __('%d item', 'bricks-static-pro'),
            /* translators: %d is an item count (plural). */
            'nItems'             => __('%d items', 'bricks-static-pro'),
            /* translators: %d is a count of swapped items. */
            'nSwapped'           => __(', %d swapped', 'bricks-static-pro'),
            'chooseReplacement'  => __('Choose a replacement', 'bricks-static-pro'),
            'useThisMedia'       => __('Use this image', 'bricks-static-pro'),
            'phSearchNameAlt'    => __('Search name or alt…', 'bricks-static-pro'),
            'loadingMedia'       => __('Loading images…', 'bricks-static-pro'),
            'noMediaYet'         => __('No images found yet — run a Check or Sync first so the pages are rendered.', 'bricks-static-pro'),
            'clickToReplace'     => __('Click to replace', 'bricks-static-pro'),
            'noAlt'              => __('— no alt —', 'bricks-static-pro'),
            /* translators: %1$d is a page count, %2$s the comma-joined page list. */
            'nPagesList'         => __('%1$d page: %2$s', 'bricks-static-pro'),
            /* translators: %1$d is a page count (plural), %2$s the comma-joined page list. */
            'nPagesListPlural'   => __('%1$d pages: %2$s', 'bricks-static-pro'),
            'replacedWith'       => __('↳ replaced with', 'bricks-static-pro'),
            'noMediaMatch'       => __('No images match the current filter.', 'bricks-static-pro'),
            /* translators: %d is the number of size variants covered by one swap. */
            'nSizes'             => __('%d sizes', 'bricks-static-pro'),
            'mediaNotInLibrary'  => __('not in library', 'bricks-static-pro'),
            'mediaNotInLibraryTip' => __('This image isn’t in the media library, so only this exact URL is swapped — other responsive sizes may remain.', 'bricks-static-pro'),
        ];
    }

    /**
     * Link replacer.
     *
     * @return array<string,string>
     */
    private static function links(): array {
        return [
            'linkReplacer'      => __('Link replacer', 'bricks-static-pro'),
            /* translators: %d is a link count (singular). */
            'nLink'             => __('%d link', 'bricks-static-pro'),
            /* translators: %d is a link count (plural). */
            'nLinks'            => __('%d links', 'bricks-static-pro'),
            /* translators: %d is a count of replaced links. */
            'nReplaced'         => __(', %d replaced', 'bricks-static-pro'),
            'phSearchUrlLabel'  => __('Search URL or label…', 'bricks-static-pro'),
            'linksHint'         => __('Replace a link target (<code>&lt;a&gt;</code>/button <code>href</code>) with another URL for this destination. Leave blank to keep the original.', 'bricks-static-pro'),
            'loadingLinks'      => __('Loading links…', 'bricks-static-pro'),
            'noLinksYet'        => __('No links found yet — run a Check or Sync first so the pages are rendered.', 'bricks-static-pro'),
            'noLabel'           => __('— no label —', 'bricks-static-pro'),
            /* translators: %d is a page count (singular). */
            'nPage'             => __('%d page', 'bricks-static-pro'),
            /* translators: %d is a page count (plural). */
            'nPages'            => __('%d pages', 'bricks-static-pro'),
            'phReplacementUrl'  => __('Replacement URL…', 'bricks-static-pro'),
            'noLinksMatch'      => __('No links match the current filter.', 'bricks-static-pro'),
        ];
    }

    /**
     * Video replacer.
     *
     * @return array<string,string>
     */
    private static function videos(): array {
        return [
            'videoReplacer'         => __('Video replacer', 'bricks-static-pro'),
            'videoSelectPage'       => __('Page', 'bricks-static-pro'),
            'videoPickPageFirst'    => __('Select a page above to replace its videos.', 'bricks-static-pro'),
            'noVideosOnPage'        => __('No replaceable videos on this page.', 'bricks-static-pro'),
            /* translators: %d is a video count (singular). */
            'nVideo'                => __('%d video', 'bricks-static-pro'),
            /* translators: %d is a video count (plural). */
            'nVideos'               => __('%d videos', 'bricks-static-pro'),
            'phSearchUrlTitle'      => __('Search URL or title…', 'bricks-static-pro'),
            'videosHint'            => __('Local videos swap via the media library; embeds (YouTube, Vimeo, …) take a URL <em>or</em> a video ID (like Bricks). An embed’s <code>origin=</code> is rewritten to this destination automatically when a Destination URL is set.', 'bricks-static-pro'),
            'loadingVideos'         => __('Loading videos…', 'bricks-static-pro'),
            'noVideosYet'           => __('No videos found yet — run a Check or Sync first so the pages are rendered.', 'bricks-static-pro'),
            'localFile'             => __('local file', 'bricks-static-pro'),
            'chooseReplacementVideo'=> __('Choose a replacement video', 'bricks-static-pro'),
            'useThisVideo'          => __('Use this video', 'bricks-static-pro'),
            'phReplUrlOrId'         => __('Replacement URL or video ID…', 'bricks-static-pro'),
            'noVideosMatch'         => __('No videos match the current filter.', 'bricks-static-pro'),
        ];
    }
}
