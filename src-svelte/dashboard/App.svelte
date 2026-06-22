<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../shared/api';
  import { caps } from '../shared/capabilities.svelte';
  import { PURCHASE_URL } from '../shared/upsell';
  import type { DestinationsResponse, DiscoveryMode, Status, SyncSnapshot } from '../shared/types';
  import NoticePanel from './NoticePanel.svelte';
  import DiscoveryToggle from './DiscoveryToggle.svelte';
  import MethodPanel from './MethodPanel.svelte';
  import DestinationTabs from './DestinationTabs.svelte';
  import DestinationPanel from './DestinationPanel.svelte';
  import DestinationToolbar from './DestinationToolbar.svelte';
  import ReplacementsPanel from './ReplacementsPanel.svelte';
  import ManualRunBanner from './ManualRunBanner.svelte';
  import AllDestinationsPanel from './AllDestinationsPanel.svelte';
  import ProgressPanel from './ProgressPanel.svelte';
  import ServerConfigPanel from './ServerConfigPanel.svelte';

  const freeVersion = (window as unknown as { bsData?: { version?: string } }).bsData?.version ?? '';

  let dests = $state<DestinationsResponse | null>(null);
  let status = $state<Status | null>(null);
  let sync = $state<SyncSnapshot | null>(null);
  let syncing = $state(false);
  let manualRun = $state(false);
  let runCommand = $state('');
  let topTab = $state<'destinations' | 'server'>('destinations');
  let activeTab = $state('');
  let loadError = $state('');

  let destinations = $derived(dests?.destinations ?? []);
  let activeDest = $derived(destinations.find((d) => d.id === activeTab) ?? null);

  const sleep = (ms: number): Promise<void> => new Promise((r) => setTimeout(r, ms));

  async function loadDestinations(): Promise<void> {
    try {
      dests = await api.getDestinations();
      if (!activeTab || !destinations.some((d) => d.id === activeTab) && activeTab !== 'all') {
        activeTab = destinations.length > 1 ? 'all' : (destinations[0]?.id ?? '');
      }
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function loadStatus(): Promise<void> {
    try {
      status = await api.getStatus();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  // Grace period for a spawned WP-CLI process to show a heartbeat before we
  // conclude the auto-spawn didn't launch and ask the user to run it by hand.
  const CLI_SPAWN_GRACE_MS = 8000;

  async function runActiveJob(): Promise<void> {
    if (syncing) return;
    syncing = true;
    manualRun = false;
    try {
      // The browser only drives the run itself when there is NO WP-CLI to do it
      // (driver === 'browser'). A /sync/tick web request holds a PHP worker AND
      // fires a loopback render needing a second worker — on hosts with very few
      // workers (Local on Windows runs just two php-cgi processes) the loopback
      // can route back to the busy worker and deadlock. So when WP-CLI IS present
      // (driver === 'cli') we never tick: we poll for the CLI process, and if its
      // auto-spawn never starts we ask the user to run the command instead — then
      // pick the run back up here the moment it's going.
      let driving = false;
      if (sync?.driver !== 'cli') {
        driving = (await api.syncClaim()).owner === 'browser';
      }
      const startedAt = Date.now();
      while ((sync && sync.running) || manualRun) {
        if (driving) {
          sync = await api.syncTick();
          continue;
        }

        await sleep(manualRun ? 2500 : 1500);
        sync = await api.syncStatus();

        if (sync?.cliAlive) {
          manualRun = false; // a CLI process is driving it (auto-spawn or user-run)
        } else if (sync?.running && Date.now() - startedAt > CLI_SPAWN_GRACE_MS) {
          manualRun = true; // auto-spawn didn't start — prompt the user to run it
        } else if (!sync?.running && !manualRun) {
          break; // finished, nothing pending
        }
      }
    } catch (e) {
      loadError = (e as Error).message;
    } finally {
      syncing = false;
      manualRun = false;
      // Refresh BOTH: the global status panel and the per-destination dots in
      // the toolbar (Connected / Pushed / In sync), which come from /destinations.
      void loadStatus();
      void loadDestinations();
    }
  }

  function dismissManualRun(): void {
    manualRun = false; // the run loop exits on its next tick if no job is active
  }

  async function setDiscoveryMode(mode: DiscoveryMode): Promise<void> {
    if (!status) return;
    const previous = status.discoveryMode;
    status.discoveryMode = mode; // optimistic
    try {
      const r = await api.setDiscoveryMode(mode);
      status.discoveryMode = r.discoveryMode;
    } catch (e) {
      status.discoveryMode = previous;
      loadError = (e as Error).message;
    }
  }

  async function setFabEnabled(enabled: boolean): Promise<void> {
    if (!status) return;
    const previous = status.fabEnabled;
    status.fabEnabled = enabled; // optimistic
    try {
      const r = await api.setFabEnabled(enabled);
      status.fabEnabled = r.fabEnabled;
    } catch (e) {
      status.fabEnabled = previous;
      loadError = (e as Error).message;
    }
  }

  // The exact CLI command for this run, shown if the auto-spawn doesn't start so
  // the user can run it by hand — targeting the same destination(s) they chose.
  function buildRunCommand(type: 'check' | 'sync', dest: string, prune: boolean): string {
    let cmd = status?.cli ?? 'wp bricks-static sync';
    if (type === 'check') cmd += ' --check';
    if (dest === 'all') cmd += ' --all';
    else if (dest) cmd += ` --dest=${dest}`;
    if (prune) cmd += ' --prune';
    return cmd;
  }

  async function startCheck(destId: string): Promise<void> {
    runCommand = buildRunCommand('check', destId, false);
    try {
      sync = await api.syncStart('check', { dest: destId });
      await runActiveJob();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function startSync(dest: string, prune: boolean): Promise<void> {
    const target = dest === 'all' ? 'all enabled destinations' : 'the destination';
    if (!window.confirm(`This will render the site and push to ${target}. Continue?`)) return;
    runCommand = buildRunCommand('sync', dest, prune);
    try {
      sync = await api.syncStart('sync', { dest, prune });
      await runActiveJob();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function retryUploads(): Promise<void> {
    try {
      sync = await api.syncRetry();
      await runActiveJob();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function cancelSync(): Promise<void> {
    try {
      sync = await api.syncCancel();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function resetSync(): Promise<void> {
    if (!window.confirm('Reset the push record for all destinations? The next Sync will re-upload everything.')) return;
    try {
      await api.syncReset();
      // Don't wipe the visible progress of the last run — just refresh the
      // status + per-destination dots to reflect the cleared push records.
      await Promise.all([loadStatus(), loadDestinations()]);
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function addDestination(): Promise<void> {
    try {
      dests = await api.addDestination({ name: `Destination ${destinations.length + 1}` });
      activeTab = destinations[destinations.length - 1]?.id ?? activeTab;
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function renameDestination(id: string, name: string): Promise<void> {
    try {
      dests = await api.updateDestination(id, { name });
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function removeDestination(id: string): Promise<void> {
    try {
      dests = await api.removeDestination(id);
      activeTab = destinations.length > 1 ? 'all' : (destinations[0]?.id ?? '');
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  onMount(() => {
    void (async () => {
      await loadDestinations();
      await loadStatus();
      try {
        sync = await api.syncStatus();
      } catch {
        /* ignore */
      }
    })();
  });
</script>

<div class="bs-dash bs-stack bs-stack--lg">
  <header class="bs-stack bs-stack--xs">
    <h1>
      Bricks Static
      <span class="bs-verbadge" class:bs-verbadge--pro={caps.edition === 'pro'}>
        v{freeVersion}{#if caps.proVersion} · Pro v{caps.proVersion}{/if}
      </span>
      <span class="bs-dash__by">Another plugin by <a href="https://brxprod.com" target="_blank" rel="noopener noreferrer">BRXProd</a></span>
    </h1>
    <p class="bs-dash__lead">Generate and serve static HTML versions of your site for performance.</p>
  </header>

  {#if caps.edition !== 'pro'}
    <div class="bs-freebox">
      <div class="bs-freebox__head">
        <strong class="bs-freebox__title">You're on the free version of Bricks Static</strong>
        <a class="bs-freebox__btn" href={PURCHASE_URL} target="_blank" rel="noopener noreferrer">Upgrade to Pro</a>
      </div>
      <div class="bs-freebox__cols">
        <div class="bs-freebox__col">
          <h3 class="bs-freebox__colhead">Free — this plugin</h3>
          <ul class="bs-freebox__list">
            <li>Static generation — up to {caps.maxPages} pages per sync</li>
            <li>1 destination (SFTP / FTP / FTPS)</li>
            <li>Text replacements</li>
            <li>Per-file &amp; package (zip) deploy</li>
            <li>.htaccess + nginx config, favicon</li>
            <li>Single-page sync &amp; WP-CLI</li>
          </ul>
        </div>
        <div class="bs-freebox__col bs-freebox__col--pro">
          <h3 class="bs-freebox__colhead">Pro — add-on</h3>
          <ul class="bs-freebox__list">
            <li><strong>Unlimited</strong> pages</li>
            <li><strong>Unlimited</strong> destinations + sync-all</li>
            <li>Media, Links, Videos &amp; Data replacements</li>
            <li>Gzip pre-compression (.gz)</li>
            <li>Remote pruning</li>
            <li>Sitemap.xml + robots.txt</li>
          </ul>
        </div>
      </div>
    </div>
  {/if}

  {#if loadError}<div class="bs-dash__error">{loadError}</div>{/if}

  {#if status}
    <div class="bs-globalbar">
      <DiscoveryToggle mode={status.discoveryMode} disabled={syncing} onChange={setDiscoveryMode} />
      <label class="bs-globalbar__group bs-fabtoggle">
        <input
          type="checkbox"
          checked={status.fabEnabled}
          onchange={(e) => setFabEnabled(e.currentTarget.checked)}
        />
        <span>Enable sync single button</span>
      </label>
      <div class="bs-globalbar__group bs-globalbar__notice">
        <NoticePanel isLocal={status.isLocal} cli={status.cli} wpCli={status.wpCli} inline />
      </div>
    </div>
  {/if}

  <!-- Global progress for the current/last sync, above the tabs and labelled
       with its target — visible whichever tab is open. -->
  <ProgressPanel snapshot={sync} onRetry={retryUploads} retrying={syncing} />

  <div class="bs-toptabs" role="tablist">
    <button type="button" class="bs-toptab" class:bs-toptab--active={topTab === 'destinations'} onclick={() => (topTab = 'destinations')}>
      Destinations
    </button>
    <button type="button" class="bs-toptab" class:bs-toptab--active={topTab === 'server'} onclick={() => (topTab = 'server')}>
      Destination Server Configuration
    </button>
  </div>

  {#if topTab === 'destinations'}
    {#if dests}
      <DestinationTabs {destinations} active={activeTab} onSelect={(t) => (activeTab = t)} onAdd={addDestination} onRename={renameDestination} />

      <div class="bs-panels">
        {#if activeTab === 'all'}
          <AllDestinationsPanel
            {destinations}
            running={syncing}
            onSyncAll={(prune) => startSync('all', prune)}
            onSyncOne={(id, prune) => startSync(id, prune)}
            onSelect={(id) => (activeTab = id)}
          />
        {:else if activeDest}
          {#key activeDest.id}
            <DestinationToolbar destination={activeDest} running={syncing} onSaved={loadDestinations} onCheck={startCheck} onSync={startSync} />
            <DestinationPanel
              destination={activeDest}
              capabilities={dests.capabilities}
              running={syncing}
              canRemove={destinations.length > 1}
              onSaved={loadDestinations}
              onRemove={removeDestination}
            />
            <ReplacementsPanel destination={activeDest} running={syncing} onSaved={loadDestinations} />
          {/key}
        {/if}
      </div>
    {/if}

    {#if manualRun && status}
      <ManualRunBanner command={runCommand || status.cli} onDismiss={dismissManualRun} />
    {/if}

    <div class="bs-row bs-row--between bs-controls">
      <span class="bs-controls__hint">Wiped a remote, or want a full re-upload? Reset clears the push record for every destination.</span>
      <div class="bs-row">
        {#if syncing}
          <button type="button" class="bs-link" onclick={cancelSync}>Cancel</button>
        {/if}
        <button type="button" class="bs-link" onclick={resetSync}>Reset sync state</button>
      </div>
    </div>
  {:else}
    <div class="bs-panels">
      <MethodPanel method={status?.method ?? null} />
      <ServerConfigPanel />
    </div>
  {/if}
</div>

<style>
  .bs-dash {
    padding: var(--bs-space--lg) 0;
  }

  .bs-panels {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 500px));
    gap: var(--bs-space--lg);
    align-items: stretch;
  }

  .bs-dash__lead {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--lg);
  }

  .bs-verbadge {
    display: inline-block;
    margin-left: var(--bs-space--xs);
    padding: 0 var(--bs-space--xs);
    border-radius: var(--bs-radius--pill);
    background: var(--bs-color-surface--sunken);
    border: var(--bs-border--1) solid var(--bs-color-border);
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--xs);
    font-weight: var(--bs-weight--semibold);
    vertical-align: middle;
  }

  .bs-verbadge--pro {
    background: color-mix(in srgb, var(--bs-color-accent, #7c3aed) 14%, transparent);
    border-color: var(--bs-color-accent, #7c3aed);
    color: var(--bs-color-text);
  }

  .bs-dash__by {
    margin-left: var(--bs-space--sm);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--normal);
    color: var(--bs-color-text--muted);
    vertical-align: middle;
  }

  .bs-dash__by a {
    color: var(--bs-color-primary);
    text-decoration: none;
  }

  .bs-dash__by a:hover {
    text-decoration: underline;
  }

  .bs-freebox {
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--md);
    padding: var(--bs-space--md) var(--bs-space--lg);
    border: var(--bs-border--1) solid var(--bs-color-accent, #7c3aed);
    border-radius: var(--bs-radius--lg);
    background: color-mix(in srgb, var(--bs-color-accent, #7c3aed) 6%, var(--bs-color-surface--raised));
  }

  .bs-freebox__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--bs-space--md);
    flex-wrap: wrap;
  }

  .bs-freebox__title {
    font-size: var(--bs-text--md);
  }

  .bs-freebox__cols {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--bs-space--md);
  }

  @media (min-width: 640px) {
    .bs-freebox__cols {
      grid-template-columns: 1fr 1fr;
    }
  }

  .bs-freebox__col {
    padding: var(--bs-space--sm) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface--raised);
  }

  .bs-freebox__col--pro {
    border-color: var(--bs-color-accent, #7c3aed);
    background: color-mix(in srgb, var(--bs-color-accent, #7c3aed) 8%, var(--bs-color-surface--raised));
  }

  .bs-freebox__colhead {
    margin: 0 0 var(--bs-space--2xs);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--semibold);
  }

  .bs-freebox__list {
    margin: 0;
    padding-left: var(--bs-space--lg);
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
    line-height: 1.6;
  }

  .bs-freebox__btn {
    flex: 0 0 auto;
    padding: var(--bs-space--xs) var(--bs-space--lg);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-accent, #7c3aed);
    color: #fff;
    font-weight: var(--bs-weight--semibold);
    text-decoration: none;
    white-space: nowrap;
  }

  .bs-freebox__btn:hover {
    filter: brightness(1.08);
  }

  .bs-dash__error {
    padding: var(--bs-space--sm) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-danger);
    border-radius: var(--bs-radius--md);
    color: var(--bs-color-danger);
    background: color-mix(in srgb, var(--bs-color-danger) 8%, transparent);
  }

  .bs-globalbar {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: var(--bs-space--sm) var(--bs-space--lg);
    padding: var(--bs-space--sm) var(--bs-space--md);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
  }

  /* App-bar group: a left divider between sections (toggle · button · status). */
  .bs-globalbar__group {
    padding-left: var(--bs-space--lg);
    border-left: var(--bs-border--1) solid var(--bs-color-border);
  }

  .bs-globalbar__notice {
    flex: 0 1 auto;
    min-width: 0;
  }

  .bs-fabtoggle {
    display: flex;
    align-items: center;
    gap: var(--bs-space--xs);
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-text--muted);
    white-space: nowrap;
    cursor: pointer;
  }

  @media (max-width: 640px) {
    .bs-globalbar__group {
      padding-left: 0;
      border-left: 0;
    }
  }

  .bs-toptabs {
    display: flex;
    gap: var(--bs-space--xs);
  }

  .bs-toptab {
    padding: var(--bs-space--sm) var(--bs-space--lg);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text--muted);
    font: inherit;
    font-size: var(--bs-text--md);
    font-weight: var(--bs-weight--semibold);
    cursor: pointer;
  }

  .bs-toptab--active {
    background: var(--bs-color-primary);
    border-color: transparent;
    color: var(--bs-color-primary--contrast);
  }

  .bs-controls__hint {
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--subtle);
  }

  .bs-link {
    background: none;
    border: 0;
    padding: 0;
    color: var(--bs-color-primary);
    font: inherit;
    font-size: var(--bs-text--sm);
    cursor: pointer;
  }
</style>
