<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../shared/api';
  import type { ConnectionResponse, Status, SyncSnapshot } from '../shared/types';
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

  // Drive the batched run: tick until the job is no longer running.
  async function drive(): Promise<void> {
    if (syncing) {
      return;
    }
    syncing = true;
    try {
      while (sync && sync.running) {
        sync = await api.syncTick();
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
      await drive();
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
      await drive();
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
