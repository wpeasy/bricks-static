<script lang="ts">
  import type { DestinationDisplay } from '../shared/types';

  let {
    destinations,
    running,
    onSyncAll,
    onSyncOne,
    onSelect,
  }: {
    destinations: DestinationDisplay[];
    running: boolean;
    onSyncAll: (prune: boolean) => void;
    onSyncOne: (id: string, prune: boolean) => void;
    onSelect: (id: string) => void;
  } = $props();

  let prune = $state(false);
  let enabledCount = $derived(destinations.filter((d) => d.enabled).length);
</script>

<section class="bs-card bs-stack bs-stack--sm">
  <h2>All destinations</h2>
  <p class="bs-all__lead">Render once, then push to every enabled destination in turn.</p>

  <ul class="bs-all__list">
    {#each destinations as d (d.id)}
      <li class="bs-all__item">
        <button type="button" class="bs-all__name" onclick={() => onSelect(d.id)}>
          {d.name || 'Destination'}
        </button>
        <span class="bs-all__meta">
          {d.enabled ? (d.host.value || '—') : 'disabled'}
        </span>
        <span class="bs-all__status bs-all__status--{d.status.inSync ? 'ok' : d.status.hasPushed ? 'warn' : 'off'}">
          {d.status.inSync ? 'In sync' : d.status.hasPushed ? 'Out of date' : 'Not pushed'}
        </span>
        <button type="button" class="bs-btn bs-btn--secondary" onclick={() => onSyncOne(d.id, prune)} disabled={running || !d.enabled}>
          Sync
        </button>
      </li>
    {/each}
  </ul>

  <label class="bs-all__opt">
    <input type="checkbox" bind:checked={prune} disabled={running} />
    Remove deleted files from each destination (prune)
  </label>

  <div class="bs-row">
    <button type="button" class="bs-btn bs-btn--primary" onclick={() => onSyncAll(prune)} disabled={running || enabledCount === 0}>
      {running ? 'Working…' : `Sync all (${enabledCount} enabled)`}
    </button>
  </div>
</section>

<style>
  .bs-card {
    /* Span two columns of the auto-fit panels grid (falls back to 1 when narrow). */
    grid-column: span 2;
    padding: var(--bs-space--lg);
    background: var(--bs-color-surface--raised);
    border: var(--bs-border--1) solid var(--bs-color-border);
    border-radius: var(--bs-radius--lg);
    box-shadow: var(--bs-shadow--sm);
  }

  .bs-all__lead {
    color: var(--bs-color-text--muted);
    font-size: var(--bs-text--sm);
  }

  .bs-all__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--bs-space--xs);
  }

  .bs-all__item {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    align-items: center;
    gap: var(--bs-space--sm);
    padding: var(--bs-space--xs) var(--bs-space--sm);
    border: var(--bs-border--1) solid var(--bs-color-border--subtle);
    border-radius: var(--bs-radius--md);
  }

  .bs-all__name {
    background: none;
    border: 0;
    padding: 0;
    text-align: left;
    font: inherit;
    font-weight: var(--bs-weight--medium);
    color: var(--bs-color-primary);
    cursor: pointer;
  }

  .bs-all__meta {
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
    font-family: var(--bs-font--mono);
  }

  .bs-all__status {
    font-size: var(--bs-text--xs);
    padding: var(--bs-space--3xs) var(--bs-space--xs);
    border-radius: var(--bs-radius--pill);
    background: var(--bs-color-surface--sunken);
    color: var(--bs-color-text--muted);
  }

  .bs-all__status--ok {
    color: var(--bs-color-success);
    background: color-mix(in srgb, var(--bs-color-success) 14%, transparent);
  }

  .bs-all__status--warn {
    color: var(--bs-color-warning);
    background: color-mix(in srgb, var(--bs-color-warning) 14%, transparent);
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

  .bs-all__opt {
    font-size: var(--bs-text--sm);
    color: var(--bs-color-text--muted);
  }
</style>
