<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '../shared/api';
  import type { ConnectionResponse, Status } from '../shared/types';
  import StatusPanel from './StatusPanel.svelte';
  import MethodPanel from './MethodPanel.svelte';
  import ConnectionForm from './ConnectionForm.svelte';

  let connection = $state<ConnectionResponse | null>(null);
  let status = $state<Status | null>(null);
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

  onMount(() => {
    void loadConnection();
    void loadStatus();
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
