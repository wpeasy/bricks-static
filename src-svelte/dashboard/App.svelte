<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../shared/api';
  import type { ConnectionResponse, Status, SyncSnapshot } from '../shared/types';
  import NoticePanel from './NoticePanel.svelte';
  import StatusPanel from './StatusPanel.svelte';
  import MethodPanel from './MethodPanel.svelte';
  import ActionsPanel from './ActionsPanel.svelte';
  import ProgressPanel from './ProgressPanel.svelte';
  import ServerConfigPanel from './ServerConfigPanel.svelte';
  import ConnectionForm from './ConnectionForm.svelte';

  let connection = $state<ConnectionResponse | null>(null);
  let status = $state<Status | null>(null);
  let sync = $state<SyncSnapshot | null>(null);
  let syncing = $state(false);
  let loadError = $state('');

  async function loadStatus(): Promise<void> {
    try {
      status = await api.getStatus();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function loadConnection(): Promise<void> {
    try {
      connection = await api.getConnection();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  function handleSaved(next: ConnectionResponse): void {
    connection = next;
    void loadStatus();
  }

  const sleep = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms));

  // Run the active job: if a WP-CLI process is driving it (driver = 'cli'), just
  // poll status; otherwise drive it from the browser via tick requests.
  async function runActiveJob(): Promise<void> {
    if (syncing) {
      return;
    }
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

  async function startCheck(): Promise<void> {
    try {
      sync = await api.syncStart('check');
      await runActiveJob();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function startSync(prune: boolean): Promise<void> {
    const note = prune
      ? ' Files removed locally will also be deleted from the destination.'
      : '';
    if (!window.confirm(`This will render the site and push the changed files to the destination.${note} Continue?`)) {
      return;
    }
    try {
      sync = await api.syncStart('sync', { prune });
      await runActiveJob();
    } catch (e) {
      loadError = (e as Error).message;
    }
  }

  async function resetSync(): Promise<void> {
    if (!window.confirm('Reset the local push record? The next Sync will re-upload everything.')) {
      return;
    }
    try {
      await api.syncReset();
      sync = null;
      await loadStatus();
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

  onMount(() => {
    // Load sequentially to avoid hammering low-worker setups (e.g. Local) on
    // page load. We intentionally do NOT auto-resume an in-progress run — that
    // could silently fire loopback renders; the user re-runs Sync if needed.
    void (async () => {
      await loadConnection();
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
    <p class="bs-dash__lead">
      Generate and serve static HTML versions of your site for performance.
    </p>
  </header>

  {#if loadError}
    <div class="bs-dash__error">{loadError}</div>
  {/if}

  {#if status}
    <NoticePanel isLocal={status.isLocal} cli={status.cli} wpCli={status.wpCli} />
  {/if}

  <StatusPanel {status} />
  <MethodPanel method={status?.method ?? null} />
  <ActionsPanel running={syncing} onCheck={startCheck} onSync={startSync} onCancel={cancelSync} onReset={resetSync} />
  <ProgressPanel snapshot={sync} />
  <ServerConfigPanel />

  {#if connection}
    <ConnectionForm {connection} onsaved={handleSaved} onrefresh={loadStatus} />
  {:else if !loadError}
    <p class="bs-dash__muted">Loading settings…</p>
  {/if}
</div>

<style>
  .bs-dash {
    max-width: 52rem;
    padding: var(--bs-space--lg) 0;
  }

  .bs-dash__lead {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--lg);
  }

  .bs-dash__muted {
    color: var(--bs-color-text--muted);
  }

  .bs-dash__error {
    padding: var(--bs-space--sm) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-danger);
    border-radius: var(--bs-radius--md);
    color: var(--bs-color-danger);
    background: color-mix(in srgb, var(--bs-color-danger) 8%, transparent);
  }
</style>
