<script lang="ts">
  import { untrack } from 'svelte';
  import { api } from '../shared/api';
  import type { DestinationDisplay } from '../shared/types';

  let {
    destination,
    running,
    onSaved,
    onCheck,
    onSync,
  }: {
    destination: DestinationDisplay;
    running: boolean;
    onSaved: () => void;
    onCheck: (id: string) => void;
    onSync: (id: string, prune: boolean) => void;
  } = $props();

  const d = untrack(() => destination);

  let enabled = $state(d.enabled);
  let singlePage = $state(d.includeInSinglePageSync);
  let saving = $state(false);

  let connected = $derived(destination.status.connected);
  let busy = $derived(saving || running);

  let url = $derived.by(() => {
    const explicit = destination.destinationUrl.value;
    if (explicit) return explicit;
    const host = destination.host.value;
    return host ? `https://${host}` : '';
  });

  async function toggle(field: 'enabled' | 'includeInSinglePageSync', value: boolean): Promise<void> {
    saving = true;
    try {
      await api.updateDestination(d.id, { [field]: value });
      onSaved();
    } catch {
      // Revert the optimistic switch on failure.
      if (field === 'enabled') enabled = !value;
      else singlePage = !value;
    } finally {
      saving = false;
    }
  }
</script>

<div class="bs-dtoolbar">
  <div class="bs-dstatus">
    <span class="bs-dstatus__item bs-dstatus__item--{connected ? 'on' : 'off'}"><span class="bs-dstatus__dot"></span>Connected</span>
    <span class="bs-dstatus__item bs-dstatus__item--{destination.status.hasPushed ? 'on' : 'off'}"><span class="bs-dstatus__dot"></span>Pushed</span>
    <span class="bs-dstatus__item bs-dstatus__item--{destination.status.inSync ? 'on' : 'off'}"><span class="bs-dstatus__dot"></span>In sync</span>
  </div>

  <div class="bs-dtoolbar__switches">
    <label class="bs-switch"><input type="checkbox" bind:checked={enabled} disabled={busy} onchange={() => toggle('enabled', enabled)} /> Enabled</label>
    <label class="bs-switch"><input type="checkbox" bind:checked={singlePage} disabled={busy} onchange={() => toggle('includeInSinglePageSync', singlePage)} /> Include in single-page sync</label>
  </div>

  <div class="bs-dtoolbar__actions">
    <button
      type="button"
      class="bs-btn bs-btn--secondary"
      onclick={() => onCheck(d.id)}
      disabled={busy || !connected}
      data-balloon={connected ? undefined : 'Test the connection first'}
      data-balloon-pos="down"
    >Check</button>
    <button
      type="button"
      class="bs-btn bs-btn--primary"
      onclick={() => onSync(d.id, false)}
      disabled={busy || !connected}
      data-balloon={connected ? undefined : 'Test the connection first'}
      data-balloon-pos="down"
    >Sync</button>
    {#if url}
      <a class="bs-dtoolbar__link" href={url} target="_blank" rel="noopener noreferrer">Visit site ↗</a>
    {/if}
  </div>
</div>

<style>
  .bs-dtoolbar {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--bs-space--md) var(--bs-space--lg);
    padding: var(--bs-space--sm) var(--bs-space--md);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--md);
  }

  .bs-dstatus {
    display: flex;
    flex-wrap: wrap;
    gap: var(--bs-space--md);
  }

  .bs-dstatus__item {
    display: flex;
    align-items: center;
    gap: var(--bs-space--2xs);
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }

  .bs-dstatus__dot {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: var(--bs-radius--full);
    background: var(--bs-color-text--subtle);
  }

  .bs-dstatus__item--on .bs-dstatus__dot {
    background: var(--bs-color-success);
  }

  .bs-dstatus__item--off .bs-dstatus__dot {
    background: var(--bs-color-danger);
  }

  .bs-dtoolbar__switches {
    display: flex;
    flex-wrap: wrap;
    gap: var(--bs-space--md);
  }

  .bs-switch {
    display: flex;
    align-items: center;
    gap: var(--bs-space--xs);
    font-size: var(--bs-text--sm);
  }

  .bs-dtoolbar__actions {
    display: flex;
    align-items: center;
    gap: var(--bs-space--sm);
    margin-left: auto;
  }

  .bs-btn {
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid var(--bs-color-border--strong);
    border-radius: var(--bs-radius--md);
    background: var(--bs-color-surface);
    color: var(--bs-color-text);
    font: inherit;
    font-weight: var(--bs-weight--medium);
    cursor: pointer;
  }

  .bs-btn--primary {
    background: var(--bs-color-primary);
    border-color: transparent;
    color: var(--bs-color-primary--contrast);
  }

  .bs-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  .bs-dtoolbar__link {
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-primary);
    text-decoration: none;
    white-space: nowrap;
  }

  .bs-dtoolbar__link:hover {
    text-decoration: underline;
  }
</style>
