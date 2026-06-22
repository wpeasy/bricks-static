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
            self::catalog(),
            self::allDestinations(),
            self::progress(),
            self::manualRun(),
            self::serverConfig(),
            self::textReplacements(),
            self::richText(),
            self::common()
        );
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
            'freeYouAreOn'      => __("You're on the free version of Bricks Static", 'bricks-static'),
            'upgradeToPro'      => __('Upgrade to Pro', 'bricks-static'),
            'freeThisPlugin'    => __('Free — this plugin', 'bricks-static'),
            'proAddon'          => __('Pro — add-on', 'bricks-static'),
            /* translators: %d is the page limit. */
            'freeStaticGen'     => __('Static generation — up to %d pages per sync', 'bricks-static'),
            'freeOneDest'       => __('1 destination (SFTP / FTP / FTPS)', 'bricks-static'),
            'freeTextRepl'      => __('Text replacements', 'bricks-static'),
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
            /* translators: %s is the sync target (e.g. "the destination"). */
            'confirmSync'       => __('This will render the site and push to %s. Continue?', 'bricks-static'),
            'syncTargetAll'     => __('all enabled destinations', 'bricks-static'),
            'syncTargetOne'     => __('the destination', 'bricks-static'),
            'confirmReset'      => __('Reset the push record for all destinations? The next Sync will re-upload everything.', 'bricks-static'),
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
            'whatsDifference' => __("What's the difference?", 'bricks-static'),
            'discControls'    => __('Controls which pages are discovered and exported.', 'bricks-static'),
            'tagDefault'      => __('default', 'bricks-static'),
            'discLinkedBody'  => __('Starts at your home page and follows internal links from page to page. A page that nothing links to isn\'t reachable by a visitor, so it\'s left out — this also keeps builder-only URLs (such as Bricks <code>/template/…</code> previews) out of the static site.', 'bricks-static'),
            'discAllBody'     => __('Also includes every published page, post and taxonomy archive — even ones nothing links to (orphaned content). Use this if you rely on pages reached only through JavaScript navigation, or deliberately keep unlinked landing pages.', 'bricks-static'),
            'discNote'        => __('Either way, builder/preview post types are never exported.', 'bricks-static'),
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
            'catMediaDesc'  => __('Swap images and media for other library items per destination — responsive variants are rebuilt automatically.', 'bricks-static'),
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
            'phPrune'       => __('Removing old files', 'bricks-static'),
            'phDone'        => __('Done', 'bricks-static'),
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
