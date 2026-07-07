<?php
/**
 * Translatable UI strings for the Svelte/JS dashboard.
 *
 * Single source of truth for every string the Free admin UI renders. PHP owns
 * the strings (so one `.mo` translates both PHP and JS); `Admin\Menu` localises
 * `self::all()` onto `window.bsData.i18n` and the Svelte `shared/i18n.ts` helper
 * looks each key up. Keep keys stable — they are referenced by the JS bundle.
 *
 * @package WPEasy\BricksStatic
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPEasy\BricksStatic\Support;

defined('ABSPATH') || exit;

/**
 * The dashboard i18n dictionary (key => translated string).
 */
final class I18n {

    /**
     * The full key => string map handed to the JS bundle.
     *
     * @return array<string,string>
     */
    public static function all(): array {
        return array_merge(
            self::header(),
            self::globalbar(),
            self::controls(),
            self::discovery(),
            self::notice(),
            self::method(),
            self::destination(),
            self::tabs(),
            self::toolbar(),
            self::replacements(),
            self::media(),
            self::catalog(),
            self::allDestinations(),
            self::progress(),
            self::manualRun(),
            self::serverConfig(),
            self::textReplacements(),
            self::richText(),
            self::editor(),
            self::ai(),
            self::common()
        );
    }

    /**
     * AI tools (MCP) panel.
     *
     * @return array<string,string>
     */
    private static function ai(): array {
        return [
            'aiTitle'        => __('AI tools (MCP)', 'bricks-static'),
            'aiIntro'        => __('Expose Bricks Static to AI assistants through the WordPress Abilities API. Read-only abilities (status, page list, link checks) are always available to administrators; the actions below are off by default.', 'bricks-static'),
            'aiUnavailable'  => __('Requires WordPress 6.9 or newer (the Abilities API). Read-only and action abilities are unavailable on this site.', 'bricks-static'),
            'aiConsumerNote' => __('An MCP client (e.g. the WordPress MCP plugin) is needed to actually reach these abilities.', 'bricks-static'),
            'aiAllowChanges' => __('Allow AI to change settings', 'bricks-static'),
            'aiAllowChangesHint' => __('Discovery mode and the per-page Include switch. Respects your plan limits.', 'bricks-static'),
            'aiAllowSync'    => __('Allow AI to run syncs', 'bricks-static'),
            'aiAllowSyncHint'=> __('Scan, sync, single-page sync, cancel and reset. These render pages and push to your destinations.', 'bricks-static'),
        ];
    }

    /**
     * Manual-mode post editor metabox + FAB include panel.
     *
     * @return array<string,string>
     */
    private static function editor(): array {
        return [
            'editorInclude'      => __('Include in static export', 'bricks-static'),
            'editorIncludeHint'  => __('When off, this page is left out of the static export.', 'bricks-static'),
            'editorSyncThisPage' => __('Sync this page', 'bricks-static'),
            'editorSyncing'      => __('Syncing…', 'bricks-static'),
            'editorPublishFirst' => __('Publish this page to sync it.', 'bricks-static'),
            'editorNoTargets'    => __('Enable a destination to sync this page.', 'bricks-static'),
            'editorNotPushed'    => __('Not pushed', 'bricks-static'),
            /* translators: %1$d included pages, %2$d the plan limit. */
            'editorIncludedCount'     => __('%1$d of %2$d pages included', 'bricks-static'),
            /* translators: %d is the number of included pages. */
            'editorIncludedUnlimited' => __('%d pages included', 'bricks-static'),
            'editorLimitHint'         => __('Free plan page limit reached — exclude another page, or upgrade to Pro to add more.', 'bricks-static'),
            /* translators: %d is a count of saved-but-not-exported pages. */
            'editorSavedOver'         => __('%d more saved — over your Free limit.', 'bricks-static'),
            'editorOverLimit'         => __('This page is saved but over your Free limit — it won’t export until you upgrade or free up room.', 'bricks-static'),
            /* translators: %d is a count of linked pages. */
            'editorLinksOut'   => __('This page links to %d page(s) not included:', 'bricks-static'),
            /* translators: %d is a count of pages that link here. */
            'editorLinksIn'    => __('%d included page(s) link here — they will have dead links:', 'bricks-static'),
            'editorLinksInNote'=> __('Based on the last check/sync — run a check to refresh.', 'bricks-static'),
            'editorIncludeAll' => __('Include all', 'bricks-static'),
            'editorIncluding'  => __('Including…', 'bricks-static'),
            /* translators: %d is a count that could not be included. */
            'editorLinkSkipped'=> __('%d couldn’t be included (Free limit reached).', 'bricks-static'),
        ];
    }

    /**
     * Header + free/pro upgrade box.
     *
     * @return array<string,string>
     */
    private static function header(): array {
        return [
            'appLead'           => __('Generate and serve static HTML versions of your site for performance.', 'bricks-static'),
            'byPrefix'          => __('Another plugin by', 'bricks-static'),
            'settings'          => __('Settings', 'bricks-static'),
            'styleGroup'        => __('Style', 'bricks-static'),
            'compactMode'       => __('Compact', 'bricks-static'),
            'colorScheme'       => __('Colour scheme', 'bricks-static'),
            'csAuto'            => __('Auto', 'bricks-static'),
            'csLight'           => __('Light', 'bricks-static'),
            'csDark'            => __('Dark', 'bricks-static'),
            'themeAccent'       => __('Theme', 'bricks-static'),
            'accentBlue'        => __('Blue', 'bricks-static'),
            'accentGreen'       => __('Green', 'bricks-static'),
            'accentYellow'      => __('Yellow', 'bricks-static'),
            'accentPurple'      => __('Purple', 'bricks-static'),
            'accentCustom'      => __('Custom', 'bricks-static'),
            'freeYouAreOn'      => __("You're on the free version of Bricks Static", 'bricks-static'),
            'upgradeToPro'      => __('Upgrade to Pro', 'bricks-static'),
            'freeThisPlugin'    => __('Free — this plugin', 'bricks-static'),
            'proAddon'          => __('Pro — add-on', 'bricks-static'),
            /* translators: %d is the page limit. */
            'freeStaticGen'     => __('Static generation — up to %d pages per sync', 'bricks-static'),
            'freeOneDest'       => __('Up to 2 destinations (SFTP / FTP / FTPS)', 'bricks-static'),
            'freeTextRepl'      => __('Text replacements', 'bricks-static'),
            'freeMediaRepl'     => __('Media replacements — 1 per page', 'bricks-static'),
            'freePerFile'       => __('Per-file & package (zip) deploy', 'bricks-static'),
            'freeHtaccess'      => __('.htaccess + nginx config, favicon', 'bricks-static'),
            'freeSinglePage'    => __('Single-page sync & WP-CLI', 'bricks-static'),
            'proUnlimitedPages' => __('<strong>Unlimited</strong> pages', 'bricks-static'),
            'proUnlimitedDests' => __('<strong>Unlimited</strong> destinations + sync-all', 'bricks-static'),
            'proAdvRepl'        => __('Media, Links & Videos replacements', 'bricks-static'),
            'proGzip'           => __('Gzip pre-compression (.gz)', 'bricks-static'),
            'proPrune'          => __('Remote pruning', 'bricks-static'),
            'proSitemap'        => __('Sitemap.xml + robots.txt', 'bricks-static'),
        ];
    }

    /**
     * Global toolbar + top tabs.
     *
     * @return array<string,string>
     */
    private static function globalbar(): array {
        return [
            'enableSyncButton' => __('Enable sync single button', 'bricks-static'),
            'syncGroup'        => __('Sync', 'bricks-static'),
            'concurrentSyncs'  => __('Concurrent syncs', 'bricks-static'),
            'concurrentSyncsHelp' => __('How many destinations to upload to at the same time during a multi-destination sync. Higher is faster but uses more connections. Requires WP-CLI; otherwise syncs run one at a time.', 'bricks-static'),
            'tabDestinations'  => __('Destinations', 'bricks-static'),
            'tabServerConfig'  => __('Destination Server Configuration', 'bricks-static'),
        ];
    }

    /**
     * Bottom controls + confirm dialogs.
     *
     * @return array<string,string>
     */
    private static function controls(): array {
        return [
            'resetHint'         => __('Wiped a remote, or want a full re-upload? Reset clears the push record for every destination.', 'bricks-static'),
            'btnResetSync'      => __('Reset sync state', 'bricks-static'),
            'btnConfirmReset'   => __('Confirm reset?', 'bricks-static'),
            'btnConfirmSync'    => __('Confirm sync?', 'bricks-static'),
            'btnConfirmSyncAll' => __('Sync all destinations?', 'bricks-static'),
            'destinationDefault'=> __('Destination', 'bricks-static'),
            /* translators: %d is the new destination's number. */
            'destinationNumbered' => __('Destination %d', 'bricks-static'),
        ];
    }

    /**
     * Discovery toggle + its help modal.
     *
     * @return array<string,string>
     */
    private static function discovery(): array {
        return [
            'pagesToInclude'  => __('Pages to include', 'bricks-static'),
            'onlyLinkedPages' => __('Only linked pages', 'bricks-static'),
            'allPublished'    => __('All published', 'bricks-static'),
            'manualSelection' => __('Manual selection', 'bricks-static'),
            'whatsDifference' => __("What's the difference?", 'bricks-static'),
            'discControls'    => __('Controls which pages are discovered and exported.', 'bricks-static'),
            'tagDefault'      => __('default', 'bricks-static'),
            'discManualBody'  => __('Export only the pages you choose. Each Page, Post and custom post type gets an "Include" switch — off by default, except the home page — in its editor, the Pages/Posts list and the front-end Sync panel. Nothing else is crawled in, so the export is exactly your selection.', 'bricks-static'),
            'discLinkedBody'  => __('Starts at your home page and follows internal links from page to page. A page that nothing links to isn\'t reachable by a visitor, so it\'s left out — this also keeps builder-only URLs (such as Bricks <code>/template/…</code> previews) out of the static site.', 'bricks-static'),
            'discAllBody'     => __('Also includes every published page, post and taxonomy archive — even ones nothing links to (orphaned content). Use this if you rely on pages reached only through JavaScript navigation, or deliberately keep unlinked landing pages.', 'bricks-static'),
            'discNote'        => __('Either way, builder/preview post types are never exported.', 'bricks-static'),
            'viewPagesList'   => __('View included/excluded pages', 'bricks-static'),
            'btnProcess'      => __('Process', 'bricks-static'),
            'btnProcessHint'  => __('Render the site for the current mode so the page list is accurate.', 'bricks-static'),
            'btnViewList'     => __('View processed list', 'bricks-static'),
            /* translators: %d: number of published pages not in the static export. */
            'excludedHint'    => __('%d not in export', 'bricks-static'),
            'excludedHintTip' => __('Published pages that aren’t in the static export. Click to view them.', 'bricks-static'),
            'pagesOverviewTitle' => __('Pages in the static export', 'bricks-static'),
            'tabIncluded'     => __('Included', 'bricks-static'),
            'tabExcluded'     => __('Excluded', 'bricks-static'),
            'pagesLoading'    => __('Loading…', 'bricks-static'),
            'noIncludedPages' => __('No pages are in the export yet.', 'bricks-static'),
            'noExcludedPages' => __('Every published page is in the export.', 'bricks-static'),
            'colPage'         => __('Page', 'bricks-static'),
            'colPath'         => __('Path', 'bricks-static'),
            'colNotices'      => __('Notices', 'bricks-static'),
            'colReason'       => __('Reason', 'bricks-static'),
        ];
    }

    /**
     * WP-CLI notice panel.
     *
     * @return array<string,string>
     */
    private static function notice(): array {
        return [
            'wpCliDetected'     => __('WP-CLI detected', 'bricks-static'),
            'wpCliBody'         => __('pages are rendered by a WP-CLI process, taking the load off your web server.', 'bricks-static'),
            'runFromCli'        => __('Run Sync from the command line on this host', 'bricks-static'),
            'cliWarnBody'       => __('This environment serves PHP requests one at a time, so browser-driven Sync can time out. Run it from a terminal instead:', 'bricks-static'),
            'cliFlagsHint'      => __('Add <code>--check</code> for a dry run, or <code>--prune</code> to remove deleted files.', 'bricks-static'),
            'testBrowserRender' => __('Test browser rendering', 'bricks-static'),
        ];
    }

    /**
     * Method summary panel.
     *
     * @return array<string,string>
     */
    private static function method(): array {
        return [
            'method'         => __('Method', 'bricks-static'),
            'methodLead'     => __('How the next sync will run, resolved for this site:', 'bricks-static'),
            'mDiscovery'     => __('Discovery', 'bricks-static'),
            'methodDiscLinked' => __('Internal-link crawl from the home page — only pages reachable by following links.', 'bricks-static'),
            'methodDiscAll'    => __('Every published page, post and taxonomy archive — including content nothing links to.', 'bricks-static'),
            'methodDiscManual' => __('Only the pages you mark “Include” (per-post).', 'bricks-static'),
            'mTransport'     => __('Transport', 'bricks-static'),
            'mCompression'   => __('Compression', 'bricks-static'),
            'mServerTarget'  => __('Server target', 'bricks-static'),
            'mLinks'         => __('Links', 'bricks-static'),
        ];
    }

    /**
     * Destination connection panel.
     *
     * @return array<string,string>
     */
    private static function destination(): array {
        return [
            'fldTransport'     => __('Transport', 'bricks-static'),
            'optUnavailable'   => __(' (unavailable)', 'bricks-static'),
            'optFtps'          => __('FTPS (FTP over TLS)', 'bricks-static'),
            'optFtp'           => __('FTP (insecure)', 'bricks-static'),
            'fldPort'          => __('Port', 'bricks-static'),
            'fldHost'          => __('Host', 'bricks-static'),
            'fldUsername'      => __('Username', 'bricks-static'),
            'fldPassword'      => __('Password', 'bricks-static'),
            'phSavedBlank'     => __('Saved — leave blank to keep', 'bricks-static'),
            'fldRemotePath'    => __('Remote path (web root)', 'bricks-static'),
            'phRemoteEmpty'    => __('(empty = FTP login dir)', 'bricks-static'),
            'fldDestUrl'       => __('Destination URL (optional)', 'bricks-static'),
            'fldSubPath'       => __('Served from sub-path (optional)', 'bricks-static'),
            'deployFastOk'     => __('Fast deploy: one package, extracted on the destination.', 'bricks-static'),
            'deployFastGuessed'=> __('Fast deploy enabled — the URL was guessed from the host. Set a Destination URL above to be sure it keeps working.', 'bricks-static'),
            'deployPerFileErr' => __('Per-file upload — package deploy hit an error and was turned off here.', 'bricks-static'),
            'deployNoZip'      => __('Per-file upload — ZipArchive isn’t available on this server.', 'bricks-static'),
            'deployPerFile'    => __('Per-file upload (slower). Set a Destination URL above to enable fast package deploy (needs PHP on the host).', 'bricks-static'),
            'btnReTesting'     => __('Re-testing…', 'bricks-static'),
            'btnReTest'        => __('Re-test', 'bricks-static'),
            'msgSaved'         => __('Saved.', 'bricks-static'),
            'btnTest'          => __('Test', 'bricks-static'),
            'confirmRemoveDest'=> __('Remove this destination?', 'bricks-static'),
            'btnYesRemove'     => __('Yes, remove', 'bricks-static'),
            'btnRemoveDest'    => __('Remove destination', 'bricks-static'),
        ];
    }

    /**
     * Destination tabs.
     *
     * @return array<string,string>
     */
    private static function tabs(): array {
        return [
            'allDestinations' => __('All Destinations', 'bricks-static'),
            'destNameAria'    => __('Destination name', 'bricks-static'),
            'dblClickRename'  => __('Double-click to rename', 'bricks-static'),
            'tabOff'          => __('off', 'bricks-static'),
            'multiDestReqPro' => __('Multiple destinations — Requires Pro', 'bricks-static'),
            'addDestAria'     => __('Add destination', 'bricks-static'),
        ];
    }

    /**
     * Per-destination toolbar.
     *
     * @return array<string,string>
     */
    private static function toolbar(): array {
        return [
            'enableFirst'   => __('Enable this destination first', 'bricks-static'),
            'testConnFirst' => __('Test the connection first', 'bricks-static'),
            'stConnected'   => __('Connected', 'bricks-static'),
            'stPushed'      => __('Pushed', 'bricks-static'),
            'stInSync'      => __('In sync', 'bricks-static'),
            'swEnabled'     => __('Enabled', 'bricks-static'),
            'btnCheck'      => __('Check', 'bricks-static'),
            'btnChecking'   => __('Checking…', 'bricks-static'),
            'btnCheckHint'  => __('Preview what a sync would change on this destination — compares the last render, uploads nothing.', 'bricks-static'),
            'btnSync'       => __('Sync', 'bricks-static'),
            'visitSite'     => __('Visit site ↗', 'bricks-static'),
        ];
    }

    /**
     * Replacements accordion shell.
     *
     * @return array<string,string>
     */
    private static function replacements(): array {
        return [
            'replacements'    => __('Replacements', 'bricks-static'),
            'replacementsSub' => __('(applied to this destination only · saved automatically)', 'bricks-static'),
        ];
    }

    /**
     * Media replacer panel (Free, per page). Shared UI keys here are also read by
     * the Pro Video/Link panels via the merged dictionary.
     *
     * @return array<string,string>
     */
    private static function media(): array {
        return [
            'mediaReplacer'        => __('Media replacer', 'bricks-static'),
            /* translators: %d is the per-page limit. */
            'mediaPerPageLimit'    => __('Free: %d per page', 'bricks-static'),
            'mediaSelectPage'      => __('Page', 'bricks-static'),
            'mediaSelectPagePh'    => __('Select a page…', 'bricks-static'),
            'mediaPickPageFirst'   => __('Select a page above to replace its media.', 'bricks-static'),
            'noRenderForMedia'     => __('Process the site first, then choose a page to replace its media.', 'bricks-static'),
            'noMediaOnPage'        => __('No replaceable images on this page.', 'bricks-static'),
            /* translators: %d is the per-page limit. */
            'mediaCapReached'      => __('Free allows %d media replacement per page. Upgrade to Pro for unlimited.', 'bricks-static'),
            'loadingMedia'         => __('Loading media…', 'bricks-static'),
            'chooseReplacement'    => __('Choose a replacement', 'bricks-static'),
            'useThisMedia'         => __('Use this media', 'bricks-static'),
            'clickToReplace'       => __('Click to replace', 'bricks-static'),
            'noAlt'                => __('— no alt —', 'bricks-static'),
            'replacedWith'         => __('↳ replaced with', 'bricks-static'),
            'noMediaMatch'         => __('No media match the current filter.', 'bricks-static'),
            /* translators: %d is the number of image sizes. */
            'nSizes'               => __('%d sizes', 'bricks-static'),
            'mediaNotInLibrary'    => __('not in library', 'bricks-static'),
            'mediaNotInLibraryTip' => __('This image isn’t in the media library, so only this exact URL is swapped — other responsive sizes may remain.', 'bricks-static'),
            'mediaUnavailable'     => __('The WordPress media library is unavailable on this page.', 'bricks-static'),
            'btnReplace'           => __('Replace…', 'bricks-static'),
            'btnRemove'            => __('Remove', 'bricks-static'),
        ];
    }

    /**
     * Replacement catalogue entries (accordion titles + locked-row descriptions).
     *
     * @return array<string,string>
     */
    private static function catalog(): array {
        return [
            'catText'       => __('Text', 'bricks-static'),
            'catMedia'      => __('Media', 'bricks-static'),
            'catLinks'      => __('Links', 'bricks-static'),
            'catVideos'     => __('Videos', 'bricks-static'),
            'catTextDesc'   => __('Find and replace visible text on this destination only.', 'bricks-static'),
            'catMediaDesc'  => __('Pick a page, then swap one of its images for another library item — responsive variants are rebuilt automatically. Free allows one per page; Pro is unlimited.', 'bricks-static'),
            'catLinksDesc'  => __('Rewrite link and button targets per destination, without touching body text.', 'bricks-static'),
            'catVideosDesc' => __('Swap local or embedded videos and fix embed origins for the destination domain.', 'bricks-static'),
        ];
    }

    /**
     * All-destinations panel.
     *
     * @return array<string,string>
     */
    private static function allDestinations(): array {
        return [
            'allDestHeading' => __('All destinations', 'bricks-static'),
            'allDestLead'    => __('Render once, then push to every enabled destination in turn.', 'bricks-static'),
            'stDisabled'     => __('disabled', 'bricks-static'),
            'stOutOfDate'    => __('Out of date', 'bricks-static'),
            'stNotPushed'    => __('Not pushed', 'bricks-static'),
            'pruneOption'    => __('Remove deleted files from each destination (prune)', 'bricks-static'),
            'btnWorking'     => __('Working…', 'bricks-static'),
            /* translators: %d is the number of enabled destinations. */
            'syncAllN'       => __('Sync all (%d enabled)', 'bricks-static'),
        ];
    }

    /**
     * Sync progress panel.
     *
     * @return array<string,string>
     */
    private static function progress(): array {
        return [
            'progress'      => __('Progress', 'bricks-static'),
            'phCollect'     => __('Collecting URLs', 'bricks-static'),
            'phRender'      => __('Rendering pages', 'bricks-static'),
            'phAssets'      => __('Processing assets', 'bricks-static'),
            'phFinalize'    => __('Finalising', 'bricks-static'),
            'phPackage'     => __('Packaging & deploying', 'bricks-static'),
            'phUpload'      => __('Uploading', 'bricks-static'),
            'phDeploy'      => __('Deploying', 'bricks-static'),
            'phPrune'       => __('Removing old files', 'bricks-static'),
            'phDone'        => __('Done', 'bricks-static'),
            'tgt_done'      => __('Done', 'bricks-static'),
            'tgt_error'     => __('Failed', 'bricks-static'),
            'tgt_cancelled' => __('Cancelled', 'bricks-static'),
            'tgt_active'    => __('Uploading…', 'bricks-static'),
            'tgtPackaging'  => __('Packaging…', 'bricks-static'),
            'zipFallbackNotice' => __('Uploaded file-by-file: the PHP runtime running this sync has no Zip (ZipArchive) extension, so the faster single-package deploy wasn’t available. The result is identical, just slower. This is common with Local’s bundled WP-CLI PHP — most production hosts include Zip and will package automatically.', 'bricks-static'),
            /* translators: %s is the reason package deploy failed. */
            'pkgFallbackNotice' => __('The faster one-shot package deploy didn’t complete — %s. Files were uploaded individually instead: same result, just slower.', 'bricks-static'),
            'phError'       => __('Error', 'bricks-static'),
            'phCancelled'   => __('Cancelled', 'bricks-static'),
            'lblPages'      => __('Pages', 'bricks-static'),
            'lblAssets'     => __('Assets', 'bricks-static'),
            'lblDeploy'     => __('Deploy', 'bricks-static'),
            'lblUploads'    => __('Uploads', 'bricks-static'),
            /* translators: %d is a file count. */
            'nFiles'        => __('%d files', 'bricks-static'),
            /* translators: %1$d is a file count, %2$s a human size (e.g. "1.2 MB"). */
            'statsSite'     => __('Site: %1$d files · %2$s', 'bricks-static'),
            /* translators: %d is a count of removed files. */
            'nRemoved'      => __('%d removed', 'bricks-static'),
            /* translators: %d is a count of skipped pages. */
            'nSkipped'      => __('%d skipped', 'bricks-static'),
            /* translators: %d is a count of pages flagged as not static-friendly. */
            'nNotStatic'    => __('%d not static-friendly', 'bricks-static'),
            /* translators: %d is a count of errors. */
            'nErrors'       => __('%d errors', 'bricks-static'),
            /* translators: %d is a count of failed uploads (singular). */
            'nFailedUpload' => __('%d failed upload', 'bricks-static'),
            /* translators: %d is a count of failed uploads (plural). */
            'nFailedUploads'=> __('%d failed uploads', 'bricks-static'),
            /* translators: %d is the free page limit. */
            'freePlanLimit' => __('<strong>Free plan renders up to %d pages.</strong> Additional pages weren\'t synced.', 'bricks-static'),
            /* translators: %d is a count of failed files (singular). */
            'nFileFailed'   => __('%d file failed to upload.', 'bricks-static'),
            /* translators: %d is a count of failed files (plural). */
            'nFilesFailed'  => __('%d files failed to upload.', 'bricks-static'),
            'btnRetrying'   => __('Retrying…', 'bricks-static'),
            /* translators: %d is a count of uploads to retry (singular). */
            'btnRetryUpload'  => __('Retry %d upload', 'bricks-static'),
            /* translators: %d is a count of uploads to retry (plural). */
            'btnRetryUploads' => __('Retry %d uploads', 'bricks-static'),
            /* translators: %d is the error count. */
            'summaryErrors'   => __('Errors (%d)', 'bricks-static'),
            /* translators: %d is the skipped count. */
            'summarySkipped'  => __('Skipped (%d)', 'bricks-static'),
            /* translators: %d is the count of pages that won't work on static. */
            'summaryCompat'   => __('Won\'t work on static (%d) — forms / dynamic endpoints (notice only; pages are still uploaded)', 'bricks-static'),
        ];
    }

    /**
     * Manual-run banner.
     *
     * @return array<string,string>
     */
    private static function manualRun(): array {
        return [
            'manualRunTitle' => __('Run this in your terminal to render & sync', 'bricks-static'),
            'manualRunBody'  => __('This host serves only a couple of PHP requests at once, so the browser can\'t render reliably here. WP-CLI does it without that limit. In Local, right-click the site → <em>Open site shell</em>, then run:', 'bricks-static'),
            'manualRunHint'  => __('Add <code>--check</code> for a dry run, or <code>--prune</code> to remove deleted files. Progress will appear here automatically once it starts.', 'bricks-static'),
        ];
    }

    /**
     * Server-config panel.
     *
     * @return array<string,string>
     */
    private static function serverConfig(): array {
        return [
            'serverConfig'     => __('Server config', 'bricks-static'),
            'btnHide'          => __('Hide', 'bricks-static'),
            'btnView'          => __('View', 'bricks-static'),
            'serverConfigLead' => __('The <code>.htaccess</code> is uploaded to the destination automatically (Apache/LiteSpeed). On nginx, paste the snippet below into your server block.', 'bricks-static'),
        ];
    }

    /**
     * Text-replacements panel + editor.
     *
     * @return array<string,string>
     */
    private static function textReplacements(): array {
        return [
            'textReplTitle'  => __('Text replacements', 'bricks-static'),
            'optional'       => __('(optional)', 'bricks-static'),
            'btnAdd'         => __('+ Add', 'bricks-static'),
            'textReplWarn'   => __('Literal find/replace, applied only to visible text content between tags — never attributes, scripts or markup. Be specific to avoid unintended matches.', 'bricks-static'),
            'noTextRepl'     => __('No text replacements yet.', 'bricks-static'),
            'lblFind'        => __('Find', 'bricks-static'),
            'lblReplace'     => __('Replace', 'bricks-static'),
            'valEmpty'       => __('(empty)', 'bricks-static'),
            'richEmpty'      => __('<em>(empty)</em>', 'bricks-static'),
            'confirmDelete'  => __('Confirm delete', 'bricks-static'),
            'keep'           => __('Keep', 'bricks-static'),
            'edit'           => __('Edit', 'bricks-static'),
            'delete'         => __('Delete', 'bricks-static'),
            'modalAddText'   => __('Add text replacement', 'bricks-static'),
            'modalEditText'  => __('Edit text replacement', 'bricks-static'),
            'phTextToFind'   => __('Text to find', 'bricks-static'),
            'lblReplaceWith' => __('Replace with', 'bricks-static'),
            'replFormatAria' => __('Replacement format', 'bricks-static'),
            'formatPlain'    => __('Plain', 'bricks-static'),
            'formatRich'     => __('Rich', 'bricks-static'),
            'phReplaceWith'  => __('Replace with', 'bricks-static'),
            'btnAddShort'    => __('Add', 'bricks-static'),
        ];
    }

    /**
     * Rich-text editor toolbar tooltips.
     *
     * @return array<string,string>
     */
    private static function richText(): array {
        return [
            'rtParagraph'    => __('Paragraph', 'bricks-static'),
            'rtH1'           => __('Heading 1', 'bricks-static'),
            'rtH2'           => __('Heading 2', 'bricks-static'),
            'rtH3'           => __('Heading 3', 'bricks-static'),
            'rtBold'         => __('Bold', 'bricks-static'),
            'rtItalic'       => __('Italic', 'bricks-static'),
            'rtUnderline'    => __('Underline', 'bricks-static'),
            'rtBullet'       => __('Bullet list', 'bricks-static'),
            'rtNumbered'     => __('Numbered list', 'bricks-static'),
            'rtLink'         => __('Link', 'bricks-static'),
            'rtClearFormat'  => __('Clear formatting', 'bricks-static'),
            'rtHtmlSource'   => __('HTML source', 'bricks-static'),
            'rtVisualEditor' => __('Visual editor', 'bricks-static'),
            'rtEditHtml'     => __('Edit HTML', 'bricks-static'),
            'rtLinkPrompt'   => __('Link URL', 'bricks-static'),
            'rtPlaceholder'  => __('Replace with…', 'bricks-static'),
            'rtFormatting'   => __('Formatting', 'bricks-static'),
        ];
    }

    /**
     * Strings shared across components.
     *
     * @return array<string,string>
     */
    private static function common(): array {
        return [
            'loading'       => __('Loading…', 'bricks-static'),
            'btnCancel'     => __('Cancel', 'bricks-static'),
            'btnSave'       => __('Save', 'bricks-static'),
            'btnSaving'     => __('Saving…', 'bricks-static'),
            'btnTesting'    => __('Testing…', 'bricks-static'),
            'btnCopy'       => __('Copy', 'bricks-static'),
            'btnCopied'     => __('Copied', 'bricks-static'),
            'btnDismiss'    => __('Dismiss', 'bricks-static'),
            'close'         => __('Close', 'bricks-static'),
            'requiresPro'   => __('Requires Pro', 'bricks-static'),
            'upsellExpired' => __('Your Bricks Static Pro license has expired — renew to re-enable this feature. Your saved settings are kept.', 'bricks-static'),
            'renewLicense'  => __('Renew license', 'bricks-static'),
        ];
    }
}
