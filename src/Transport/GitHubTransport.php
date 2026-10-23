<?php
/**
 * GitHub Pages transport.
 *
 * @package WPEasy\ShipStatic
 * @since   2.1.3
 */

declare(strict_types=1);

namespace WPEasy\ShipStatic\Transport;

use WPEasy\ShipStatic\Deploy\GitHubAuthException;
use WPEasy\ShipStatic\Deploy\GitHubClient;
use WPEasy\ShipStatic\Deploy\GitHubConflictException;
use WPEasy\ShipStatic\Deploy\GitHubRateLimitException;
use WPEasy\ShipStatic\Deploy\GitHubValidationException;
use WPEasy\ShipStatic\Support\Url;

defined('ABSPATH') || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are plain text for JSON responses (rendered as text by the dashboard) and WP-CLI, never echoed into HTML; esc_html() here would show entities to the user.

/**
 * Publishes the export to GitHub Pages by committing it to a branch through
 * the Git Data API (`docs/GITHUB_PAGES_PLAN.md`).
 *
 * Each sync compares git blob SHAs computed locally against the branch's
 * tree, uploads only changed files (text inline in the tree request, binaries
 * as blobs), then makes ONE commit and moves the branch fast-forward. A sync
 * with nothing changed makes no commit at all. Because GitHub limits writes to
 * ~500 an hour, the publish is a {@see ResumableSnapshotTransport}: a large
 * first sync pauses and resumes instead of failing.
 *
 * What we own on the branch: the folder (`/` = the whole branch, `/docs` =
 * only `docs/`). Inside it the branch mirrors the export, except the keep list
 * (`CNAME`, README/LICENSE files, `.github/` — filter
 * `shipstatic_github_keep_paths`). We always add `.nojekyll`.
 *
 * The export puts pages under the "Served from sub-path" folder (right for a
 * web root that is the domain). A project site's repository root IS that
 * sub-path (`<owner>.github.io/<repo>/`), so the sub-path prefix is stripped
 * when mapping export paths to repository paths.
 */
final class GitHubTransport implements TransportInterface, ResumableSnapshotTransport {

    /**
     * Default per-file cap. GitHub refuses files over 100 MB, and a blob
     * travels base64 inside JSON (~3× the file in PHP memory), so stay well
     * under. Filter: `shipstatic_github_max_file_bytes` (max 100 MiB).
     */
    public const MAX_FILE_BYTES = 25 * 1024 * 1024;

    /**
     * Seconds of work per step, so a browser-driven tick never times out.
     */
    private const STEP_SECONDS = 20;

    /**
     * Minimum gap between writes — keeps under GitHub's 80 content-creating
     * requests per minute.
     */
    private const WRITE_GAP_US = 800000;

    /**
     * Target JSON size of one create-tree request.
     */
    private const TREE_BATCH_BYTES = 4 * 1024 * 1024;

    /**
     * Extensions sent inline as text (when the bytes are valid UTF-8).
     */
    private const TEXT_EXT = ['html', 'htm', 'css', 'js', 'mjs', 'json', 'map', 'xml', 'txt', 'svg', 'webmanifest', 'csv', 'md'];

    private GitHubClient $client;

    private string $owner;

    private string $repo;

    private string $branch;

    private string $folder;

    private string $destination_url;

    private bool $connected = false;

    /**
     * The repository, read on connect().
     *
     * @var array<string,mixed>
     */
    private array $repository = [];

    /**
     * Microtime of the last write (for pacing).
     */
    private float $last_write = 0.0;

    /**
     * @param array<string,mixed> $config Destination connection config.
     */
    public function __construct(array $config) {
        $this->owner           = (string) ($config['ghOwner'] ?? '');
        $this->repo            = (string) ($config['ghRepo'] ?? '');
        $this->branch          = (string) ($config['ghBranch'] ?? '') !== '' ? (string) $config['ghBranch'] : 'gh-pages';
        $this->folder          = (string) ($config['ghFolder'] ?? '') === '/docs' ? '/docs' : '/';
        $this->destination_url = (string) ($config['url'] ?? '');
        $this->client          = new GitHubClient($this->owner, $this->repo, (string) ($config['ghToken'] ?? ''));
    }

    /**
     * Always usable — needs nothing beyond the WP HTTP API and sha1.
     */
    public static function is_available(): bool {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function connect(): void {
        if (!$this->client->configured()) {
            throw new GitHubAuthException('Enter the GitHub owner, repository and access token for this destination first.');
        }
        if (!GitHubClient::valid_branch($this->branch)) {
            throw new \RuntimeException('Enter a valid branch name for this destination.');
        }
        $this->repository = $this->client->repository();
        $this->connected  = true;
    }

    /**
     * @inheritdoc
     */
    public function disconnect(): void {
        $this->connected = false;
    }

    /**
     * The connection test behind the dashboard's Test button: the token can
     * read the repository and write to it, plus what GitHub Pages reports.
     *
     * @return array{ok:bool,message:string,suggestedUrl:string,warnings:array<int,string>}
     */
    public function check(): array {
        $this->connect();

        if (!$this->client->can_write()) {
            return [
                'ok'           => false,
                'message'      => sprintf('The token can read %s/%s but not write to it. Edit the token on GitHub and set Contents to “Read and write”.', $this->owner, $this->repo),
                'suggestedUrl' => '',
                'warnings'     => [],
            ];
        }

        $warnings  = [];
        $suggested = '';
        $where     = $this->folder === '/docs' ? $this->branch . ' /docs' : $this->branch;

        try {
            $site = $this->client->pages_site();
        } catch (GitHubAuthException $e) {
            $site = false; // Token has no Pages: Read — status unknown.
        }

        if (is_array($site)) {
            $suggested = esc_url_raw((string) ($site['html_url'] ?? ''));
            $source    = (array) ($site['source'] ?? []);
            $src_where = (string) ($source['branch'] ?? '') . ((string) ($source['path'] ?? '/') === '/docs' ? ' /docs' : '');
            if (($site['build_type'] ?? '') === 'workflow') {
                $warnings[] = 'GitHub Pages is set to deploy with GitHub Actions. Change Settings → Pages → Source to “Deploy from a branch” and choose ' . $where . '.';
            } elseif ($src_where !== '' && $src_where !== $where) {
                $warnings[] = sprintf('GitHub Pages publishes from %s, but this destination writes to %s. Change Settings → Pages on GitHub (or the branch here) so they match.', $src_where, $where);
            }
            $pages = sprintf('GitHub Pages is on and serves %s', $suggested !== '' ? $suggested : 'this repository');
        } elseif ($site === null) {
            $pages = $this->branch === 'gh-pages' && $this->folder === '/'
                ? 'GitHub Pages is off — GitHub turns it on automatically when the first sync creates the gh-pages branch'
                : sprintf('GitHub Pages is off — after the first sync, turn it on in the repository’s Settings → Pages (Deploy from a branch: %s)', $where);
        } else {
            $pages = 'add the Pages: Read-only permission to the token to see the GitHub Pages status here';
        }

        if ($this->folder === '/' && $this->branch === (string) ($this->repository['default_branch'] ?? '')) {
            $warnings[] = sprintf('“%s” is the repository’s default branch: every file on it except README, LICENSE and .github/ will be replaced by the site. If it holds source code, use a separate branch (gh-pages) or the /docs folder.', $this->branch);
        }

        return [
            'ok'           => true,
            'message'      => sprintf('Connected to %s/%s with write access; %s', $this->owner, $this->repo, $pages),
            'suggestedUrl' => $suggested,
            'warnings'     => $warnings,
        ];
    }

    /**
     * @inheritdoc
     *
     * Runs the resumable publish to completion, sleeping through rate-limit
     * pauses. The Runner uses {@see deploy_snapshot_step()} instead.
     */
    public function deploy_snapshot(array $files, ?callable $progress = null): array {
        $state = [];
        do {
            $result = $this->deploy_snapshot_step($files, $state, $progress);
            if (!empty($result['pending']) && $result['waitUntil'] > time()) {
                sleep(min(60, $result['waitUntil'] - time()));
            }
        } while (!empty($result['pending']));

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function deploy_snapshot_step(array $files, array &$state, ?callable $progress = null): array {
        $out = ['ok' => false, 'pending' => false, 'waitUntil' => 0, 'retryable' => true, 'uploaded' => 0, 'skipped' => [], 'errors' => [], 'message' => ''];
        $say = static function (string $stage) use ($progress): void {
            if ($progress !== null) {
                $progress($stage);
            }
        };
        $deadline = microtime(true) + self::STEP_SECONDS;

        try {
            if (!$this->connected) {
                $this->connect();
            }

            if (($state['stage'] ?? '') === '') {
                $say('Comparing with the GitHub branch…');
                $state = $this->plan($files, $state);
                if ($state['stage'] === 'done') {
                    return $this->finish($out, $state, '');
                }
            }

            if ($state['stage'] === 'blobs') {
                $total = count($state['binaries']);
                foreach ($state['binaries'] as $i => $item) {
                    if (isset($state['uploaded'][$item['sha']])) {
                        continue;
                    }
                    if (microtime(true) > $deadline) {
                        return $this->more($out, sprintf('Uploading images and other files to GitHub… (%d of %d)', $i, $total));
                    }
                    $say(sprintf('Uploading images and other files to GitHub… (%d of %d)', $i + 1, $total));
                    $bytes = (string) file_get_contents($item['src']);
                    $this->pace();
                    $sha = $this->client->create_blob($bytes);
                    if ($sha !== $item['sha']) {
                        throw new \RuntimeException(sprintf('GitHub stored %s with different contents than were sent.', $item['path']));
                    }
                    $state['uploaded'][$sha] = true;
                }
                $state['stage']  = 'trees';
                $state['cursor'] = 0;
                $state['tree']   = $state['baseTree'];
            }

            if ($state['stage'] === 'trees') {
                $entries = $this->entries($state);
                $total   = count($entries);
                while ($state['cursor'] < $total) {
                    if (microtime(true) > $deadline) {
                        return $this->more($out, 'Building the commit on GitHub…');
                    }
                    [$batch, $next] = $this->next_batch($entries, (int) $state['cursor']);
                    $say(sprintf('Building the commit on GitHub… (%d of %d changes)', $next, $total));
                    $this->pace();
                    try {
                        $state['tree'] = $this->client->create_tree($batch, $state['tree']);
                    } catch (GitHubValidationException $e) {
                        // A blob uploaded before a long pause is gone (or the
                        // branch was rewritten): upload again, once.
                        if (!empty($state['reuploaded'])) {
                            throw $e;
                        }
                        $state['reuploaded'] = true;
                        $state['uploaded']   = [];
                        $state['stage']      = 'blobs';
                        return $this->more($out, 'Re-uploading files GitHub no longer has…');
                    }
                    $state['cursor'] = $next;
                }
                $state['stage'] = 'commit';
            }

            if ($state['stage'] === 'commit') {
                $say('Publishing the commit on GitHub…');
                $this->verify((string) $state['tree'], (array) $state['expect']);

                $message = sprintf('ShipStatic %s: %d changed, %d removed', defined('SHIPSTATIC_VERSION') ? SHIPSTATIC_VERSION : '', (int) $state['changed'], count($state['deletes']));
                /**
                 * Filters the commit message of a GitHub Pages sync.
                 *
                 * @param string $message Commit message.
                 * @param int    $changed Files added or changed.
                 * @param int    $removed Files removed.
                 */
                $message = (string) apply_filters('shipstatic_github_commit_message', $message, (int) $state['changed'], count($state['deletes']));

                $this->pace();
                $commit = $this->client->create_commit($message, (string) $state['tree'], $state['head'] !== null ? [(string) $state['head']] : []);
                try {
                    $this->pace();
                    $this->client->set_branch($this->branch, $commit, $state['head'] === null);
                } catch (GitHubConflictException $e) {
                    // Someone pushed meanwhile: re-read and rebuild on top of
                    // their commit, once — never force-push over it. Blobs
                    // already uploaded are remembered, so they aren't resent.
                    if (!empty($state['conflicted'])) {
                        throw new \RuntimeException($e->getMessage() . ' Sync again once nobody else is pushing to the ' . $this->branch . ' branch.');
                    }
                    $state = ['uploaded' => $state['uploaded'], 'conflicted' => true];
                    return $this->more($out, 'The branch changed on GitHub — rebuilding on top of it…');
                }

                return $this->finish($out, $state, $commit);
            }

            throw new \RuntimeException('Unknown GitHub publish stage.');
        } catch (GitHubRateLimitException $e) {
            $out['pending']   = true;
            $out['waitUntil'] = $e->wait_until();
            $out['message']   = sprintf(
                'Waiting for GitHub’s upload limit — continues at %s (%d of %d files sent). Keep this page open, or let the WP-CLI runner carry on.',
                wp_date((string) get_option('time_format'), $e->wait_until()),
                count((array) ($state['uploaded'] ?? [])),
                count((array) ($state['binaries'] ?? []))
            );
        } catch (GitHubAuthException $e) {
            $out['retryable'] = false;
            $out['message']   = $e->getMessage();
        } catch (\Throwable $e) {
            $out['message'] = $e->getMessage();
        }

        return $out;
    }

    /**
     * Read the branch and work out what changes.
     *
     * @param array<string,string> $files Export-relative path => local file.
     * @param array<string,mixed>  $prev  Previous state (keeps `uploaded`, `conflicted`).
     * @return array<string,mixed> New state.
     */
    private function plan(array $files, array $prev): array {
        $head      = $this->client->branch_head($this->branch);
        $base_tree = $head !== null ? $this->client->commit_tree($head) : null;
        $remote    = $base_tree !== null ? $this->client->tree_files($base_tree) : [];

        $folder = $this->folder === '/docs' ? 'docs/' : '';
        $strip  = ltrim(Url::base_path(), '/');
        $strip  = $strip !== '' ? $strip . '/' : '';
        $max    = (int) min(100 * 1024 * 1024, max(1, (int) apply_filters('shipstatic_github_max_file_bytes', self::MAX_FILE_BYTES)));

        $state = [
            'stage'      => 'blobs',
            'head'       => $head,
            'baseTree'   => $base_tree,
            'binaries'   => [],
            'texts'      => [],
            'generated'  => [],
            'deletes'    => [],
            'expect'     => [],
            'skipped'    => [],
            'errors'     => [],
            'changed'    => 0,
            'uploaded'   => (array) ($prev['uploaded'] ?? []),
            'conflicted' => !empty($prev['conflicted']),
        ];

        foreach ($files as $relative => $src) {
            $path = $folder . ($strip !== '' && str_starts_with($relative, $strip) ? substr($relative, strlen($strip)) : $relative);
            $size = is_file($src) ? (int) filesize($src) : -1;
            if ($size < 0) {
                $state['skipped'][] = $relative;
                $state['errors'][]  = $relative . ': staged file missing';
                continue;
            }
            if ($size > $max) {
                $state['skipped'][] = $relative;
                $state['errors'][]  = sprintf('%s: larger than the %d MiB per-file limit for GitHub Pages', $relative, (int) round($max / 1048576));
                continue;
            }

            $sha                    = self::blob_sha_file($src, $size);
            $state['expect'][$path] = $sha;
            if (($remote[$path] ?? '') === $sha) {
                continue;
            }
            $state['changed']++;
            if (in_array(strtolower((string) pathinfo($path, PATHINFO_EXTENSION)), self::TEXT_EXT, true) && mb_check_encoding((string) file_get_contents($src), 'UTF-8')) {
                $state['texts'][] = ['path' => $path, 'src' => $src];
            } else {
                $state['binaries'][] = ['path' => $path, 'src' => $src, 'sha' => $sha];
            }
        }

        // Files we generate: `.nojekyll` always (otherwise Pages runs Jekyll
        // and drops anything starting with "_"), `CNAME` for a custom domain
        // when the branch has none (GitHub writes one when the domain is set
        // in the repository settings — we never replace or remove it).
        $generated = [$folder . '.nojekyll' => ''];
        $host      = strtolower((string) wp_parse_url($this->destination_url, PHP_URL_HOST));
        if ($host !== '' && !str_ends_with($host, '.github.io') && !isset($remote[$folder . 'CNAME'])) {
            $generated[$folder . 'CNAME'] = $host . "\n";
        }
        foreach ($generated as $path => $content) {
            $sha                    = self::blob_sha($content);
            $state['expect'][$path] = $sha;
            if (($remote[$path] ?? '') !== $sha) {
                $state['generated'][$path] = $content;
                $state['changed']++;
            }
        }

        foreach (array_keys($remote) as $path) {
            if ($folder !== '' && !str_starts_with($path, $folder)) {
                continue; // Outside our folder — never touched.
            }
            if (!array_key_exists($path, $state['expect']) && !$this->kept(substr($path, strlen($folder)))) {
                $state['deletes'][] = $path;
            }
        }

        if ($state['changed'] === 0 && $state['deletes'] === []) {
            $state['stage'] = 'done';
        }

        return $state;
    }

    /**
     * Whether a folder-relative path is on the keep list (never deleted).
     *
     * @param string $path Folder-relative path.
     */
    private function kept(string $path): bool {
        /**
         * Filters the paths (relative to the published folder) a GitHub Pages
         * sync never deletes. An entry ending in "/" keeps a whole folder.
         *
         * @param array<int,string> $keep Paths.
         */
        $keep = (array) apply_filters('shipstatic_github_keep_paths', ['CNAME', 'README.md', 'README', 'LICENSE', 'LICENSE.md', 'LICENSE.txt', '.gitignore', '.gitattributes', '.github/']);
        foreach ($keep as $k) {
            $k = (string) $k;
            if ($path === $k || (str_ends_with($k, '/') && str_starts_with($path, $k))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every tree entry this publish makes: changed binaries (by SHA), changed
     * text and generated files (inline), then deletions (last, so the batch
     * that removes a path always runs on a tree that still has it).
     *
     * @param array<string,mixed> $state State.
     * @return array<int,array<string,mixed>>
     */
    private function entries(array $state): array {
        $entries = [];
        foreach ((array) $state['binaries'] as $item) {
            $entries[] = ['path' => $item['path'], 'mode' => '100644', 'type' => 'blob', 'sha' => $item['sha']];
        }
        foreach ((array) $state['texts'] as $item) {
            $entries[] = ['path' => $item['path'], 'mode' => '100644', 'type' => 'blob', 'src' => $item['src']];
        }
        foreach ((array) $state['generated'] as $path => $content) {
            $entries[] = ['path' => (string) $path, 'mode' => '100644', 'type' => 'blob', 'content' => (string) $content];
        }
        foreach ((array) $state['deletes'] as $path) {
            $entries[] = ['path' => (string) $path, 'mode' => '100644', 'type' => 'blob', 'sha' => null];
        }

        return $entries;
    }

    /**
     * The next create-tree batch from $cursor, reading inline text as it goes
     * and stopping near {@see TREE_BATCH_BYTES}.
     *
     * @param array<int,array<string,mixed>> $entries All entries.
     * @param int                            $cursor  First entry to include.
     * @return array{0:array<int,array<string,mixed>>,1:int} Batch + next cursor.
     */
    private function next_batch(array $entries, int $cursor): array {
        $batch = [];
        $bytes = 0;
        $count = count($entries);
        for ($i = $cursor; $i < $count; $i++) {
            $entry = $entries[$i];
            if (isset($entry['src'])) {
                $entry['content'] = (string) file_get_contents((string) $entry['src']);
                unset($entry['src']);
            }
            $size = strlen((string) wp_json_encode($entry));
            if ($batch !== [] && $bytes + $size > self::TREE_BATCH_BYTES) {
                break;
            }
            $batch[] = $entry;
            $bytes  += $size;
        }

        return [$batch, $i];
    }

    /**
     * Check the built tree holds exactly the bytes we meant to publish: the
     * SHA GitHub computed for each file must equal ours (catches any
     * re-encoding of inline text). One read.
     *
     * @param string               $tree   Tree SHA.
     * @param array<string,string> $expect Path => blob SHA.
     */
    private function verify(string $tree, array $expect): void {
        $actual = $this->client->tree_files($tree);
        foreach ($expect as $path => $sha) {
            if (($actual[$path] ?? '') !== $sha) {
                throw new \RuntimeException(sprintf('GitHub’s copy of %s does not match the export — nothing was published. Sync again.', $path));
            }
        }
    }

    /**
     * Result for "call again".
     *
     * @param array<string,mixed> $out     Result skeleton.
     * @param string              $message Stage message.
     * @return array<string,mixed>
     */
    private function more(array $out, string $message): array {
        $out['pending'] = true;
        $out['message'] = $message;

        return $out;
    }

    /**
     * Result for a finished publish (or nothing to publish).
     *
     * @param array<string,mixed> $out    Result skeleton.
     * @param array<string,mixed> $state  Final state.
     * @param string              $commit New commit SHA ('' = no commit needed).
     * @return array<string,mixed>
     */
    private function finish(array $out, array $state, string $commit): array {
        $out['ok']       = true;
        $out['uploaded'] = (int) $state['changed'];
        $out['skipped']  = array_values((array) $state['skipped']);
        $out['errors']   = array_values((array) $state['errors']);
        $out['message']  = $commit === ''
            ? 'GitHub Pages is already up to date — no commit needed.'
            : sprintf('Published to GitHub Pages: %d file(s) changed, %d removed (commit %s). GitHub takes a few seconds to update the site.', (int) $state['changed'], count((array) $state['deletes']), substr($commit, 0, 7));

        return $out;
    }

    /**
     * Keep writes under GitHub's per-minute limit.
     */
    private function pace(): void {
        $gap = (int) ((microtime(true) - $this->last_write) * 1000000);
        if ($this->last_write > 0 && $gap < self::WRITE_GAP_US) {
            usleep(self::WRITE_GAP_US - $gap);
        }
        $this->last_write = microtime(true);
    }

    /**
     * Git blob SHA of a string: sha1("blob <len>\0<bytes>").
     *
     * @param string $bytes Contents.
     */
    public static function blob_sha(string $bytes): string {
        return sha1('blob ' . strlen($bytes) . "\0" . $bytes);
    }

    /**
     * Git blob SHA of a file, streamed.
     *
     * @param string $path Local file.
     * @param int    $size Its size in bytes.
     */
    public static function blob_sha_file(string $path, int $size): string {
        $ctx = hash_init('sha1');
        hash_update($ctx, 'blob ' . $size . "\0");
        // Streamed (WP_Filesystem has no partial-read API) so a large file is
        // never held in memory whole.
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Could not read a staged file for hashing.');
        }
        try {
            hash_update_stream($ctx, $fh);
        } finally {
            fclose($fh);
        }
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose

        return hash_final($ctx);
    }

    /**
     * Not used: snapshot destinations never upload per file.
     *
     * @inheritdoc
     */
    public function put(string $local_path, string $remote_path): void {
        throw new \RuntimeException('GitHub Pages destinations publish whole snapshots; per-file upload is not supported.');
    }

    /**
     * @inheritdoc
     */
    public function mkdir(string $remote_path): void {
        // No directories in git — paths are keys.
    }

    /**
     * @inheritdoc
     */
    public function exists(string $remote_path): bool {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function rename(string $from, string $to): bool {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function get(string $remote_path): string {
        return '';
    }

    /**
     * Not used: a file is removed by leaving it out of the next snapshot.
     *
     * @inheritdoc
     */
    public function delete(string $remote_path): bool {
        return false;
    }
}
