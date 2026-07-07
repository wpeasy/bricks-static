<script lang="ts">
  import { caps } from '../shared/capabilities.svelte';

  const version = (window as unknown as { bsData?: { version?: string } }).bsData?.version ?? '';

  const sections = [
    { id: 'free-vs-pro', label: 'Free vs Pro' },
    { id: 'features', label: 'Features' },
    { id: 'how', label: 'How it works' },
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
      body: 'Push to any number of FTP, explicit-FTPS, or SFTP destinations, each enabled independently. SFTP verifies the server host key (trust-on-first-use). Credentials are encrypted at rest (AES-256-GCM).',
    },
    {
      title: 'Fast package deploy',
      body: 'Instead of one slow FTP transfer per file, it zips the changed files + a one-shot helper, uploads both, and extracts server-side over HTTPS — collapsing hundreds of round trips into one. Falls back to per-file uploads automatically when a host can’t run it.',
    },
    {
      title: 'Per-destination replacements',
      body: 'Text (plain or rich HTML) and Media (pick a page, then swap one of its images — responsive srcset variants rebuilt automatically) are included free; Links (rewrite <a>/button targets) and Videos (swap embeds/local files) are Pro. Media and Videos are per-page; each destination gets its own set, grouped in a one-open-at-a-time accordion.',
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
      title: 'gzip + server config',
      body: 'Pre-compresses text assets and uploads a managed .htaccess that serves them with the right headers; an equivalent nginx snippet is provided for manual paste.',
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
      <!-- Free vs Pro -->
      <section id="free-vs-pro" class="bs-docs__section bs-stack bs-stack--sm">
        <h2>Free vs Pro</h2>
        <p>
          Bricks Static (this plugin) is free and fully functional for small sites.
          <a href="https://brxprod.com/bricks-static" target="_blank" rel="noopener noreferrer">Bricks Static Pro</a>
          is an optional add-on that removes the limits and adds advanced tooling.
        </p>
        <div class="bs-docs__tablewrap">
          <table class="bs-docs__table">
            <thead>
              <tr><th>Feature</th><th>Free</th><th>Pro</th></tr>
            </thead>
            <tbody>
              <tr><td>Pages per sync</td><td>{caps.freeMaxPages}</td><td>Unlimited</td></tr>
              <tr><td>Destinations</td><td>{caps.freeMaxDestinations}</td><td>Unlimited + sync&#8209;all</td></tr>
              <tr><td>Text replacements</td><td>Yes</td><td>Yes</td></tr>
              <tr><td>Media replacements (per page)</td><td>1 per page</td><td>Unlimited</td></tr>
              <tr><td>Link / Video replacements</td><td>—</td><td>Yes</td></tr>
              <tr><td>Gzip pre&#8209;compression (.gz)</td><td>—</td><td>Yes</td></tr>
              <tr><td>Remote pruning (delete stale files)</td><td>—</td><td>Yes</td></tr>
              <tr><td>Sitemap.xml + robots.txt</td><td>—</td><td>Yes</td></tr>
              <tr><td>Per&#8209;file &amp; package (zip) deploy</td><td>Yes</td><td>Yes</td></tr>
              <tr><td>.htaccess + nginx, favicon, single&#8209;page sync, WP&#8209;CLI</td><td>Yes</td><td>Yes</td></tr>
            </tbody>
          </table>
        </div>
      </section>

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
          <li><strong>Deploy</strong> only what changed (delta against the last push) via package deploy or per-file upload.</li>
        </ol>
        <p class="bs-docs__muted">
          <strong>Check</strong> is a dry run: it renders and catalogues everything (and lists anything that won’t work on a static host) without uploading. <strong>Sync</strong> renders and uploads. <strong>Reset sync state</strong> forgets every destination’s push record so the next sync re-uploads in full.
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
          <summary>Single-page sync says “No destinations are enabled”</summary>
          <p>Turn on <strong>Include in single-page sync</strong> on at least one destination (its toolbar). Only those destinations receive single-page pushes.</p>
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
wp bricks-static sync --prune    # also remove remote files no longer present</pre>
        <ul class="bs-docs__list">
          <li><strong>Cache:</strong> the staging copy lives under <code>wp-content/cache/bricks-static</code> (or uploads if that isn’t writable).</li>
          <li><strong>Server config:</strong> the managed <code>.htaccess</code> is uploaded automatically; the <em>Destination Server Configuration</em> tab has the nginx equivalent to paste manually.</li>
          <li><strong>Filters:</strong> behaviour is adjustable via <code>bs_*</code> hooks (e.g. <code>bs_generate_sitemaps</code>, <code>bs_include_favicon</code>, <code>bs_seed_from_sitemap</code>, <code>bs_excluded_post_types</code>).</li>
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

  .bs-docs__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--ab-space-4);
  }

  .bs-docs__tablewrap {
    overflow-x: auto;
  }

  .bs-docs__table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--ab-text-sm);
  }

  .bs-docs__table th,
  .bs-docs__table td {
    padding: var(--ab-space-2) var(--ab-space-3);
    border: 1px solid var(--ab-color-border);
    text-align: left;
    vertical-align: top;
  }

  .bs-docs__table thead th {
    background: var(--ab-color-bg);
    font-weight: var(--ab-weight-semibold);
  }

  .bs-docs__table td:nth-child(2),
  .bs-docs__table th:nth-child(2),
  .bs-docs__table td:nth-child(3),
  .bs-docs__table th:nth-child(3) {
    width: 22%;
    white-space: nowrap;
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
