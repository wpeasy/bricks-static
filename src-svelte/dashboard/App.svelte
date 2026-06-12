<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../shared/api';
  import type { DestinationsResponse, DiscoveryMode, Status, SyncSnapshot } from '../shared/types';
  import NoticePanel from './NoticePanel.svelte';
  import DiscoveryToggle from './DiscoveryToggle.svelte';
  import MethodPanel from './MethodPanel.svelte';
  import DestinationTabs from './DestinationTabs.svelte';
  import DestinationPanel from './DestinationPanel.svelte';
  import DestinationToolbar from './DestinationToolbar.svelte';
  import ReplacementsPanel from './ReplacementsPanel.svelte';
  import AllDestinationsPanel from './AllDestinationsPanel.svelte';
  import ProgressPanel from './ProgressPanel.svelte';
  import ServerConfigPanel from './ServerConfigPanel.svelte';

  let dests = $state<DestinationsResponse | null>(null);
  let status = $state<Status | null>(null);
  let sync = $state<SyncSnapshot | null>(null);
  let syncing = $state(false);
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

  // Grace period for a spawned WP-CLI process to show a heartbeat before the
  // browser concludes the spawn never launched and drives the job itself.
  const CLI_SPAWN_GRACE_MS = 8000;

  async function runActiveJob(): Promise<void> {
    if (syncing) return;
    syncing = true;
    try {
      // When a WP-CLI process drives the run we only POLL read-only status — we
      // must NOT also tick from the browser. On Local a /sync/tick web request
      // holds a php-fpm worker AND fires a loopback render, which saturates the
      // worker pool and deadlocks the whole site. The CLI process renders
      // without that contention, even when a single page is slow.
      //
      // But the detached spawn can silently fail to launch from the web-server
      // process. We watch for the CLI heartbeat: if none appears within a grace
      // window, the browser tries to CLAIM the driver lock and take over ticking.
      // The atomic claim guarantees only one driver ever advances the job, so a
      // browser tick and a (late) CLI process can't both run loopback renders and
      // starve Local's tiny worker pool — the deadlock that broke this before.
      let driving = false;
      if (sync?.driver !== 'cli') {
        driving = (await api.syncClaim()).owner === 'browser';
      }
      const startedAt = Date.now();
      while (sync && sync.running) {
        if (driving) {
          sync = await api.syncTick();
        } else {
          await sleep(1500);
          sync = await api.syncStatus();
          if (!sync?.cliAlive && Date.now() - startedAt > CLI_SPAWN_GRACE_MS) {
            // No CLI heartbeat — try to take over. We only drive if we win the
            // claim; if the CLI just grabbed it, keep polling.
            driving = (await api.syncClaim()).owner === 'browser';
          }
        }
      }
    } catch (e) {
      loadError = (e as Error).message;
    } finally {
      syncing = false;
      void loadStatus();
    }
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

  async function startCheck(destId: string): Promise<void> {
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
    if (!window.confirm('Reset the local push record? The next Sync will re-upload everything.')) return;
    try {
      await api.syncReset();
      sync = null;
      await loadStatus();
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
    <h1>Bricks Static</h1>
    <p class="bs-dash__lead">Generate and serve static HTML versions of your site for performance.</p>
  </header>

  {#if loadError}<div class="bs-dash__error">{loadError}</div>{/if}

  {#if status}
    <div class="bs-globalbar">
      <DiscoveryToggle mode={status.discoveryMode} disabled={syncing} onChange={setDiscoveryMode} />
      <div class="bs-globalbar__notice">
        <NoticePanel isLocal={status.isLocal} cli={status.cli} wpCli={status.wpCli} inline />
      </div>
    </div>
  {/if}

  <div class="bs-toptabs" role="tablist">
    <button type="button" class="bs-toptab" class:bs-toptab--active={topTab === 'destinations'} onclick={() => (topTab = 'destinations')}>
      Destinations
    </button>
    <button type="button" class="bs-toptab" class:bs-toptab--active={topTab === 'server'} onclick={() => (topTab = 'server')}>
      Server Configuration
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

        <ProgressPanel snapshot={sync} onRetry={retryUploads} retrying={syncing} />
      </div>
    {/if}

    <div class="bs-row bs-row--between bs-controls">
      <span class="bs-controls__hint">Switched destinations or wiped the remote? Reset clears the local push record.</span>
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

  .bs-globalbar__notice {
    flex: 0 1 auto;
    min-width: 0;
    /* Divider between the toggle and the status, like an app-bar group. */
    padding-left: var(--bs-space--lg);
    border-left: var(--bs-border--1) solid var(--bs-color-border);
  }

  @media (max-width: 640px) {
    .bs-globalbar__notice {
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
