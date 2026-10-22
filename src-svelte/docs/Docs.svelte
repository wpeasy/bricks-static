<script lang="ts">
  const version = (window as unknown as { bsData?: { version?: string } }).bsData?.version ?? '';

  const sections = [
    { id: 'features', label: 'Features' },
    { id: 'how', label: 'How it works' },
    { id: 'connector', label: 'Connector' },
    { id: 'cloudflare', label: 'Cloudflare' },
    { id: 'issues', label: 'Common issues' },
    { id: 'troubleshooting', label: 'Troubleshooting' },
    { id: 'cli', label: 'CLI & reference' },
  ];

  const features = [
    {
      title: 'Static generation',
      body: 'Renders each page through a normal request and captures the final HTML — builder-agnostic, so it works with Bricks, Gutenberg and anything else the theme outputs.',
    },
    {
      title: 'Page discovery',
      body: '“Only linked pages” crawls from the home page following internal links (orphaned/builder URLs stay out). “All published” additionally includes every published post and taxonomy archive. A generated sitemap also seeds discovery.',
    },
    {
      title: 'Multiple destinations',
      body: 'Push to any number of destinations, each enabled independently and each deploying via FTP / FTPS / SFTP, the Connector or Cloudflare. SFTP verifies the server host key (trust-on-first-use). Credentials and pairing secrets are encrypted at rest (AES-256-GCM).',
    },
    {
      title: 'Connector (HTTPS) — no FTP account',
      body: 'Pair a destination, upload one small PHP file to its web root, and every sync from then on travels to that file over signed HTTPS as a single package. The file holds no password (only a fingerprint of a secret that stays on your site), writes only inside its own folder, and refuses anything a server could execute. Re-pair to rotate, Revoke to disconnect.',
    },
    {
      title: 'Cloudflare — no web host at all',
      body: 'Publish straight to Cloudflare’s global network as a static Worker, with just an account ID and an API token. Each sync sends Cloudflare the full file list, uploads only what it doesn’t already have, then switches the whole site over in one step — removed pages disappear automatically. Cache headers are published for you; Cloudflare handles compression.',
    },
    {
      title: 'Fast package deploy',
      body: 'Instead of one slow FTP transfer per file, it zips the changed files + a one-shot helper, uploads both, and extracts server-side over HTTPS — collapsing hundreds of round trips into one. Falls back to per-file uploads automatically when a host can’t run it.',
    },
    {
      title: 'Per-destination replacements',
      body: 'Text (plain or rich HTML), Media (pick a page, then swap any of its images — responsive srcset variants rebuilt automatically), Links (rewrite <a>/button targets) and Videos (swap embeds/local files) — all included, no limits. Media and Videos are per-page; each destination gets its own set, grouped in a one-open-at-a-time accordion.',
    },
    {
      title: 'Sitemaps, robots.txt & favicon',
      body: 'A sitemap index + per-type sitemaps and a robots.txt are generated and uploaded, with their URLs rewritten to each destination’s domain. A /favicon.ico is shipped from your real favicon, the WordPress Site Icon, or a generated default.',
    },
    {
      title: 'Single-page sync',
      body: 'Push just one changed page (plus any new pages it links to, so you never publish a dead link) from a draggable floating button on the front end and inside the Bricks editor — where it saves the editor first.',
    },
    {
      title: 'Export ZIP (manual deploy)',
      body: 'Every destination also has a Manual deploy section: "Export ZIP" packages the current render into a downloadable zip you upload and extract yourself — no server access needed. Uses that destination’s configured replacements, same as a real Sync, and never touches its sync stats since nothing is uploaded. Includes gzip .gz files, sitemap.xml and robots.txt.',
    },
    {
      title: 'gzip + server config',
      body: 'Pre-compresses text assets and uploads a managed .htaccess that serves them with the right headers. nginx serves the output as-is; an optional tuning snippet is provided, and "Check server" verifies what the destination is actually doing.',
    },
  ];
</script>

<div class="ab-ui bs-docs bs-stack bs-stack--lg">
  <header class="bs-stack bs-stack--xs">
    <h1>Documentation{#if version}<span class="bs-docs__ver">v{version}</span>{/if}</h1>
    <p class="bs-docs__lead">How Bricks Static works, plus fixes for the issues you’re most likely to hit.</p>
  </header>

  <div class="bs-docs__layout">
    <nav class="bs-docs__toc" aria-label="Contents">
      <span class="bs-docs__toc-title">On this page</span>
      {#each sections as s}
        <a href={`#${s.id}`}>{s.label}</a>
      {/each}
    </nav>

    <div class="bs-docs__content bs-stack bs-stack--lg">
      <!-- Features -->
      <section id="features" class="bs-docs__section">
        <h2>Features</h2>
        <div class="bs-docs__grid">
          {#each features as f}
            <div class="bs-docs__feature">
              <h3>{f.title}</h3>
              <p>{f.body}</p>
            </div>
          {/each}
        </div>
      </section>

      <!-- How it works -->
      <section id="how" class="bs-docs__section bs-stack bs-stack--sm">
        <h2>How it works</h2>
        <ol class="bs-docs__steps">
          <li><strong>Discover</strong> the pages to export (home-page crawl and/or all published, plus the sitemap).</li>
          <li><strong>Render</strong> each page through a loopback request and rewrite absolute site URLs to root-relative so the copy works on any domain.</li>
          <li><strong>Collect assets</strong> (CSS, JS, images, fonts) referenced by those pages.</li>
          <li><strong>Transform per destination</strong> — apply that destination’s text/media/link replacements and rewrite sitemap/robots URLs to its domain.</li>
          <li><strong>Deploy</strong> only what changed (delta against the last push) — over FTP/SFTP (as a package with a one-shot helper, or per file) or through the Connector. Cloudflare destinations instead receive the complete file list each time and upload only the files Cloudflare doesn’t already hold, then go live in one step.</li>
        </ol>
        <p class="bs-docs__muted">
          <strong>Check</strong> is a dry run: it renders and catalogues everything (and lists anything that won’t work on a static host) without uploading. <strong>Sync</strong> renders and uploads. <strong>Export ZIP</strong> packages the current render for one destination into a downloadable zip instead of uploading it — for manual deploys, with no effect on that destination’s sync stats. <strong>Reset sync state</strong> forgets every destination’s push record so the next sync re-uploads in full.
        </p>
      </section>

      <!-- Connector -->
      <section id="connector" class="bs-docs__section bs-stack bs-stack--sm">
        <h2>Deploying with the Connector</h2>
        <p class="bs-docs__muted">
          The Connector replaces the FTP account. You upload a single PHP file — the <em>connector</em> — to the folder your static site is served from, once. After that, each sync sends the changed files to that connector over an encrypted, signed connection as one package, which is both simpler to set up and faster than uploading file by file. Each destination uses <strong>either</strong> FTP / SFTP <strong>or</strong> the Connector; pick one under <em>Deploy via</em>.
        </p>

        <h3>Set-up</h3>
        <ol class="bs-docs__steps">
          <li>On the destination, enter the <strong>Destination URL</strong> — the public <code>https://</code> address of the folder the static site is served from — and choose <strong>Connector (HTTPS)</strong> under <em>Deploy via</em>. Press <strong>Save</strong>.</li>
          <li>Press <strong>Pair</strong>. The plugin generates a pairing for this destination only and shows a <strong>Download bs-connector-…php</strong> link. The link works once and expires after ten minutes; if you miss it, press <em>Re-pair</em> for a new one.</li>
          <li>Upload that file, unchanged, to the folder served at the Destination URL — next to where <code>index.html</code> will go. Any method works: your host’s file manager, SFTP, a one-off drag into cPanel.</li>
          <li>Press <strong>Test</strong>. A good result reads “Connector reached — PHP x.y, package deploy available, N GB free”. <strong>Check</strong> and <strong>Sync</strong> then work exactly as they do for FTP destinations, and so does single-page sync.</li>
        </ol>

        <h3>Requirements</h3>
        <ul class="bs-docs__list">
          <li>The destination must run <strong>PHP 7.4 or newer</strong> for that folder. Purely static hosts can’t run the connector — for Cloudflare, use the <a href="#cloudflare">Cloudflare</a> deploy method; for others (Netlify, S3), Export ZIP or their own deploy tooling.</li>
          <li>The Destination URL must be <strong>HTTPS</strong> (the pairing secret travels with every request). <code>*.local</code> / <code>*.test</code> development hosts are exempt.</li>
          <li>If the destination’s PHP has <code>ZipArchive</code>, syncs go up as one package and are extracted there. Without it, files are sent one at a time — same result, slower.</li>
          <li>The folder must be writable by PHP. Test tells you if it isn’t.</li>
        </ul>

        <h3>Managing a pairing</h3>
        <ul class="bs-docs__list">
          <li><strong>Re-pair (new file)</strong> — generates a fresh pairing and a new file. The file currently on the destination stops working immediately, so upload the new one before the next sync. Use it if you lose the download, suspect the secret leaked, or moved the site to a new server.</li>
          <li><strong>Revoke</strong> — forgets the pairing on this site. The connector file on the destination then refuses every request; delete it when convenient.</li>
          <li><strong>Rebuild push record</strong> — after <em>Reset sync state</em> or a re-pair, the plugin no longer knows what’s on the destination and would re-upload everything. This asks the connector for an inventory and marks every file that’s already there and identical as done, so the next Sync sends only what really changed. Files it finds that Bricks Static never uploaded are left alone. Needs a current render (Process first).</li>
          <li><strong>Check server</strong> (Destination Server Configuration tab) reads the destination’s exact web server through the connector, even behind a CDN.</li>
        </ul>

        <h3>What the connector can and can’t do</h3>
        <p class="bs-docs__muted">
          The file is made for one destination and one source site. It contains only a fingerprint of the pairing secret, never the secret itself, so nothing reusable leaks even if a host mistakenly serves it as text. Every request must carry a valid signature, a timestamp within five minutes and a never-before-seen nonce; after ten bad requests it refuses further bad ones for ten minutes (correctly signed requests from your site still get through). It only works over HTTPS — your site won’t send it anything if the Destination URL is changed to <code>http://</code>. It only ever writes inside its own folder, never through symlinks, never anything a server could run (<code>.php</code> and the like, including <code>x.php.txt</code>, <code>.user.ini</code>, <code>web.config</code>), and it accepts an <code>.htaccess</code> (in any folder) only if every line is a caching, rewrite, redirect or index/error-page rule — or was already in that file on your server. It can read back only the <code>.htaccess</code> it manages. In short: a leaked secret could at most overwrite your static pages — it can’t run code on your server or read anything else. Delete the file at any time to disconnect.
        </p>
      </section>

      <!-- Cloudflare -->
      <section id="cloudflare" class="bs-docs__section bs-stack bs-stack--sm">
        <h2>Deploying to Cloudflare</h2>
        <p class="bs-docs__muted">
          The Cloudflare deploy method publishes your static site directly to Cloudflare’s global network — no web host, no PHP and no FTP account. Under the hood it is a <em>Cloudflare Worker with static assets</em> (Cloudflare’s current product for static sites, and the successor to Cloudflare Pages for new projects); the Worker runs none of your code, it simply serves the files. Choose <strong>Cloudflare</strong> under <em>Deploy via</em>.
        </p>

        <h3>Set-up</h3>
        <ol class="bs-docs__steps">
          <li>In the Cloudflare dashboard open <strong>Workers &amp; Pages</strong> and copy your <strong>Account ID</strong> (on the right of the overview page).</li>
          <li>Create an API token: <strong>My Profile → API Tokens → Create Token → Custom token</strong>, with the single permission <strong>Account → Workers Scripts → Edit</strong>, limited to that account. Copy the token — Cloudflare shows it only once.</li>
          <li>On the destination, choose <strong>Cloudflare</strong>, then enter the Account ID, the token, and a <strong>Worker name</strong> for this site (lowercase letters, numbers and dashes, e.g. <code>my-site</code>). The Worker doesn’t need to exist; the first sync creates it.</li>
          <li>Press <strong>Test</strong>. A good result reads “Cloudflare token accepted — Worker “my-site” will be served at https://my-site.&lt;you&gt;.workers.dev”. If the Destination URL is empty it is filled with that address. Press <strong>Save</strong>.</li>
          <li><strong>Check</strong> and <strong>Sync</strong> as usual. Single-page sync works too.</li>
        </ol>

        <h3>Using your own domain</h3>
        <p class="bs-docs__muted">
          After the first sync, open the Worker in Cloudflare (<strong>Worker → Settings → Domains &amp; Routes → Add → Custom domain</strong>) and attach your domain — it must be on Cloudflare DNS. Then set the destination’s <strong>Destination URL</strong> to that domain and Sync again, so the sitemap, robots.txt and replacements use it. While the Destination URL is a custom domain, the plugin leaves the <code>workers.dev</code> address switched off so there is no duplicate public copy.
        </p>

        <h3>How a Cloudflare sync differs</h3>
        <ul class="bs-docs__list">
          <li><strong>Whole-site snapshots.</strong> Every sync sends Cloudflare the full list of files with a fingerprint of each; Cloudflare replies with the ones it doesn’t have, only those are uploaded, and the new version replaces the old one in a single step. Visitors never see a half-updated site, so there is no holding page.</li>
          <li><strong>No pruning needed.</strong> A page you remove from the export is simply absent from the next snapshot and stops being served — whether or not “prune” is on.</li>
          <li><strong>No <code>.htaccess</code> or <code>.gz</code> files.</strong> Cloudflare compresses (gzip/brotli) itself. The cache policy (assets cached for a year, HTML always revalidated) is published as Cloudflare <code>_headers</code> rules with each deploy; <strong>Check server</strong> confirms it is in effect.</li>
          <li><strong>Clean URLs.</strong> <code>/about/</code> serves <code>about/index.html</code>, and the export’s <code>404.html</code> (if any) is used for missing pages.</li>
          <li><strong>History.</strong> Every sync, including a single-page sync, creates a new version of the Worker, visible (and restorable) in Cloudflare under <em>Deployments</em>.</li>
        </ul>

        <h3>Limits</h3>
        <ul class="bs-docs__list">
          <li><strong>25 MiB per file.</strong> Larger files (usually videos) are left out of the deploy and listed in the result. Host them elsewhere (Cloudflare R2, YouTube, Vimeo) and point to them with a Videos replacement.</li>
          <li><strong>20,000 files</strong> per site on Cloudflare’s Free plan, 100,000 on paid plans.</li>
          <li><strong>Nothing runs server-side</strong> — forms that post back to WordPress, search and comments don’t work on the static copy (true of every static host).</li>
          <li>Cloudflare rate-limits its API; the plugin waits and retries automatically, but a first sync of a very large site can take a few minutes.</li>
        </ul>

        <h3>Security</h3>
        <p class="bs-docs__muted">
          The API token is encrypted at rest like FTP passwords and is never shown again or sent to the browser. Requests go only to <code>api.cloudflare.com</code> over verified HTTPS. Give the token only the <em>Workers Scripts → Edit</em> permission on one account — then a leaked token could change Workers on that account, but nothing else (DNS, billing, other accounts). Roll the token in Cloudflare at any time and paste the new one into the destination.
        </p>
      </section>

      <!-- Common issues -->
      <section id="issues" class="bs-docs__section bs-stack bs-stack--sm">
        <h2>Common issues &amp; resolutions</h2>

        <details class="bs-docs__faq">
          <summary>A destination shows “Out of date” right after a successful sync</summary>
          <p>“In sync” is based on a signature of the current render plus that destination’s replacements and URL. If it lingers after upgrading, run <strong>Sync</strong> once more — the first sync on the new logic records the signature, and it’ll read “In sync” from then on (until content or a replacement actually changes).</p>
        </details>

        <details class="bs-docs__faq">
          <summary>Everything shows “Not published / Out of date” after an interrupted run</summary>
          <p>The render manifest is cleared at the start of a full sync and rebuilt as pages render; if a run is killed midway it can be left empty. Just run <strong>Sync</strong> again to rebuild it.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>A destination is deploying per-file instead of as a fast package</summary>
          <p>Package deploy needs <code>ZipArchive</code> on this server, a <strong>Destination URL</strong> set (so the helper can be reached), and a host that can run the uploaded PHP helper. If it failed once it’s disabled for that destination — set the URL and use <strong>Re-test</strong> on the destination to re-enable it.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>Connector Test says “No connector answered” or “Connector file not found”</summary>
          <p>The plugin reached the Destination URL but our file didn’t respond. Check that the file is in the folder served at <em>exactly</em> that URL (open <code>https://your-destination/bs-connector-…php</code> in a browser: the right answer is a small “method” message, not a 404 and not the file’s source). Some hosts refuse to run PHP in certain folders — nginx setups commonly block it under <code>uploads/</code> — so if you see the web server’s own 403 page, move the static site (and the connector) to a folder where PHP runs, or use FTP/SFTP instead.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>Connector says it “rejected this site’s credentials”</summary>
          <p>The file on the destination was paired with a different secret than the one this site holds — usually an old file left in place after a <em>Re-pair</em>, or a file copied from another site. Press <strong>Re-pair (new file)</strong>, upload the new file over the old one, and Test again. If you see “temporarily locked”, wait ten minutes first.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>“The connector file on the destination is outdated”</summary>
          <p>After a plugin update the connector may need a newer version of the file. Re-pair, upload the new file, and you’re done — nothing else on the destination changes.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>Single-page sync says “No destinations are enabled”</summary>
          <p>Turn on <strong>Include in single-page sync</strong> on at least one destination (its toolbar). Only those destinations receive single-page pushes.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>Export ZIP asks me to Process first</summary>
          <p>Like <strong>Check</strong>, Export ZIP only packages the CURRENT render — it never renders for you. If the page list has changed (or nothing’s been rendered yet), click <strong>Process</strong>, then Export ZIP again.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>SFTP fails with “host key has changed”</summary>
          <p>The plugin remembers a server’s SFTP host key on first connect and refuses to continue if it later changes (a man-in-the-middle protection). If the server was legitimately rebuilt or migrated, use <strong>Reset sync state</strong> to clear the stored key, then reconnect. If you didn’t expect a change, investigate before proceeding.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>Forms, search or other dynamic features don’t work on the static site</summary>
          <p>Expected — static HTML can’t run PHP. The <strong>Check</strong> run lists pages with forms or dynamic endpoints under “Won’t work on static”. Point forms at a third-party/JS service, or keep those pages dynamic. (Pages are still uploaded; it’s a notice, not an error.)</p>
        </details>

        <details class="bs-docs__faq">
          <summary>The floating “Sync this page” button isn’t showing</summary>
          <p>Check <strong>Enable sync single button</strong> in the dashboard’s top bar (on by default). It only appears for logged-in admins on real front-end pages and the Bricks editor — never to visitors, never inside the preview iframe, and never in a static render.</p>
        </details>
      </section>

      <!-- Troubleshooting -->
      <section id="troubleshooting" class="bs-docs__section bs-stack bs-stack--sm">
        <h2>Troubleshooting</h2>

        <details class="bs-docs__faq">
          <summary>“The connector file on the destination is outdated”</summary>
          <p>The connector was updated for security (version 2): its <code>.htaccess</code> checks are stricter and a lockout can no longer be held by strangers. Files paired before the update answer every request with this message. Press <strong>Re-pair (new file)</strong> on the destination and upload the new file in place of the old one.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>A sync stalls or never progresses (often on Local)</summary>
          <p>A browser-driven sync holds one PHP worker while its loopback page render needs another. Hosts with very few workers — Local on Windows runs just two — can deadlock. The plugin prefers <strong>WP-CLI</strong> (a separate process) and tries to auto-start it; if it can’t, a banner shows the exact command to run in a terminal. Run that command and the dashboard picks the run back up automatically.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>I changed the destination URL/host — what’s affected?</summary>
          <p>Changing where a destination points clears its push record (so the next sync re-uploads everything), re-probes package deploy, and forgets the stored SFTP host key for re-trust. Sitemap/robots URLs are regenerated for the new domain on the next sync.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>TLS verification and *.local / *.test hosts</summary>
          <p>Certificate verification is relaxed <em>only</em> for <code>localhost</code>, loopback IPs, and <code>.local</code>/<code>.test</code> hosts (which can’t hold a public certificate). It is always enforced for real public destinations.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>I need to re-upload everything from scratch</summary>
          <p>Use <strong>Reset sync state</strong> (bottom of the Destinations tab). It clears every destination’s push record and the local render manifest; the next <strong>Sync</strong> uploads in full.</p>
        </details>

        <details class="bs-docs__faq">
          <summary>Who can use these features?</summary>
          <p>Everything (the dashboard, REST endpoints, the floating button, single-page sync) requires an administrator (<code>manage_options</code>). Lower-privilege users never see or trigger it.</p>
        </details>
      </section>

      <!-- CLI -->
      <section id="cli" class="bs-docs__section bs-stack bs-stack--sm">
        <h2>CLI &amp; reference</h2>
        <p class="bs-docs__muted">Drive a sync from a terminal (useful on low-worker hosts, or for cron):</p>
        <pre class="bs-docs__code">wp bricks-static sync            # render + push to the primary destination
wp bricks-static sync --all      # push to every enabled destination
wp bricks-static sync --dest=ID  # push to one destination
wp bricks-static sync --check    # dry run (render + catalogue, no upload)
wp bricks-static sync --prune    # also remove remote files no longer present

wp bricks-static connector status                                  # pairing state per destination
wp bricks-static connector pair ID --dest-url=https://… --to=DIR   # pair + write the connector file
wp bricks-static connector test ID                                 # ping the connector
wp bricks-static connector rebuild ID                              # rebuild the push record from it
wp bricks-static connector revoke ID                               # disconnect

wp bricks-static cloudflare status                                 # Cloudflare destinations
CLOUDFLARE_API_TOKEN=… wp bricks-static cloudflare set ID --account=… --worker=my-site --token-from-env
wp bricks-static cloudflare test ID                                # check the token + account</pre>
        <ul class="bs-docs__list">
          <li><strong>Cache:</strong> the staging copy lives under <code>wp-content/cache/bricks-static</code> (or uploads if that isn’t writable).</li>
          <li><strong>Server config:</strong> the managed <code>.htaccess</code> is uploaded automatically (Apache/LiteSpeed). nginx has no per-directory config, so it serves the static site with its defaults; the <em>Server config</em> tab has an optional tuning snippet to paste, plus <strong>Check server</strong>, which probes the destination and reports the web server and whether the cache rules are in effect.</li>
          <li><strong>Filters:</strong> behaviour is adjustable via <code>bs_*</code> hooks (e.g. <code>bs_generate_sitemaps</code>, <code>bs_include_favicon</code>, <code>bs_seed_from_sitemap</code>, <code>bs_excluded_post_types</code>; for Cloudflare, <code>bs_cloudflare_headers</code> and <code>bs_cloudflare_redirects</code>).</li>
        </ul>
      </section>
    </div>
  </div>
</div>

<style>
  .bs-docs {
    padding: var(--ab-space-5);
    background: var(--ab-color-bg);
    border-radius: var(--ab-radius-lg);
    max-width: 72rem;
  }

  .bs-docs__ver {
    margin-left: var(--ab-space-3);
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-normal);
    color: var(--ab-color-text-muted);
  }

  .bs-docs__lead {
    color: var(--ab-color-text-muted);
    font-size: var(--ab-text-lg);
  }

  .bs-docs__layout {
    display: grid;
    grid-template-columns: 12rem 1fr;
    gap: var(--ab-space-6, 2rem);
    align-items: start;
  }

  @media (max-width: 720px) {
    .bs-docs__layout {
      grid-template-columns: 1fr;
    }
    .bs-docs__toc {
      position: static !important;
    }
  }

  .bs-docs__toc {
    position: sticky;
    top: 2rem;
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-1);
  }

  .bs-docs__toc-title {
    font-size: var(--ab-text-xs);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ab-color-text-muted);
    margin-bottom: var(--ab-space-1);
  }

  .bs-docs__toc a {
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
    text-decoration: none;
    padding: var(--ab-space-1) 0;
  }

  .bs-docs__toc a:hover {
    color: var(--ab-color-primary);
  }

  .bs-docs__section {
    scroll-margin-top: 2rem;
    padding: var(--ab-space-5);
    background: var(--ab-color-surface);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-lg);
    box-shadow: var(--ab-shadow-sm);
  }

  .bs-docs__section h2 {
    margin: 0 0 var(--ab-space-4);
    font-size: var(--ab-text-lg);
    font-weight: var(--ab-weight-semibold);
  }

  /* Sub-headings inside a prose section (the Connector guide). */
  .bs-docs__section > h3 {
    margin: var(--ab-space-3) 0 0;
    font-size: var(--ab-text-md);
    font-weight: var(--ab-weight-semibold);
  }

  .bs-docs__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--ab-space-4);
  }

  .bs-docs__feature {
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-1);
    padding: var(--ab-space-4);
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-surface);
  }

  .bs-docs__feature h3 {
    margin: 0;
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-semibold);
    color: var(--ab-color-text);
  }

  .bs-docs__feature p {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
    line-height: 1.55;
  }

  .bs-docs__steps,
  .bs-docs__list {
    margin: 0;
    padding-left: var(--ab-space-5);
    display: flex;
    flex-direction: column;
    gap: var(--ab-space-2);
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
    line-height: 1.55;
  }

  .bs-docs__muted {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
    line-height: 1.55;
  }

  .bs-docs__faq {
    border: 1px solid var(--ab-color-border);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-surface);
    padding: var(--ab-space-2) var(--ab-space-4);
  }

  .bs-docs__faq summary {
    cursor: pointer;
    font-size: var(--ab-text-sm);
    font-weight: var(--ab-weight-medium);
    color: var(--ab-color-text);
    padding: var(--ab-space-1) 0;
  }

  .bs-docs__faq[open] summary {
    margin-bottom: var(--ab-space-2);
  }

  .bs-docs__faq p {
    margin: 0;
    font-size: var(--ab-text-sm);
    color: var(--ab-color-text-muted);
    line-height: 1.6;
  }

  .bs-docs__code {
    margin: 0;
    padding: var(--ab-space-4);
    border-radius: var(--ab-radius-md);
    background: var(--ab-color-bg);
    color: var(--ab-color-text);
    font-family: var(--ab-font-mono, ui-monospace, monospace);
    font-size: var(--ab-text-xs);
    line-height: 1.7;
    overflow-x: auto;
    white-space: pre;
  }

  code {
    font-family: var(--ab-font-mono, ui-monospace, monospace);
    font-size: 0.92em;
    padding: 0.05em 0.35em;
    border-radius: var(--ab-radius-sm);
    background: var(--ab-color-bg);
  }
</style>
