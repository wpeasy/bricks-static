<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../shared/api';
  import type { DestinationsResponse, Status, SyncSnapshot } from '../shared/types';
  import NoticePanel from './NoticePanel.svelte';
  import StatusPanel from './StatusPanel.svelte';
  import MethodPanel from './MethodPanel.svelte';
  import DestinationTabs from './DestinationTabs.svelte';
  import DestinationPanel from './DestinationPanel.svelte';
  import AllDestinationsPanel from './AllDestinationsPanel.svelte';
  import ProgressPanel from './ProgressPanel.svelte';
  import ServerConfigPanel from './ServerConfigPanel.svelte';

  let dests = $state<DestinationsResponse | null>(null);
  let status = $state<Status | null>(null);
  let sync = $state<SyncSnapshot | null>(null);
  let syncing = $state(false);
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

  async function runActiveJob(): Promise<void> {
    if (syncing) return;
    syncing = true;
    try {
      const viaCli = sync?.driver === 'cli';
      while (sync && sync.running) {
        if (viaCli) {
          await sleep(1500);
          sync = await api.syncStatus();
        } else {
          sync = await api.syncTick();
        }
      }
    } catch (e) {
      loadError = (e as Error).message;
    } finally {
      syncing = false;
      void loadStatus();
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

  async function removeDestination(id: string): Promise<void> {
    if (!window.confirm('Remove this destination?')) return;
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
    <NoticePanel isLocal={status.isLocal} cli={status.cli} wpCli={status.wpCli} />
  {/if}

  <StatusPanel {status} />
  <MethodPanel method={status?.method ?? null} />

  {#if dests}
    <DestinationTabs {destinations} active={activeTab} onSelect={(t) => (activeTab = t)} onAdd={addDestination} />

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
        <DestinationPanel
          destination={activeDest}
          capabilities={dests.capabilities}
          running={syncing}
          canRemove={destinations.length > 1}
          onSaved={loadDestinations}
          onCheck={startCheck}
          onSync={startSync}
          onRemove={removeDestination}
        />
      {/key}
    {/if}
  {/if}

  <ProgressPanel snapshot={sync} />

  <div class="bs-row bs-row--between bs-controls">
    <span class="bs-controls__hint">Switched destinations or wiped the remote? Reset clears the local push record.</span>
    <div class="bs-row">
      {#if syncing}
        <button type="button" class="bs-link" onclick={cancelSync}>Cancel</button>
      {/if}
      <button type="button" class="bs-link" onclick={resetSync}>Reset sync state</button>
    </div>
  </div>

  <ServerConfigPanel />
</div>

<style>
  .bs-dash {
    max-width: 56rem;
    padding: var(--bs-space--lg) 0;
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
