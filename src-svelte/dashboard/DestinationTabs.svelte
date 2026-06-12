<script lang="ts">
  import type { DestinationDisplay } from '../shared/types';

  let {
    destinations,
    active,
    onSelect,
    onAdd,
  }: {
    destinations: DestinationDisplay[];
    active: string;
    onSelect: (tab: string) => void;
    onAdd: () => void;
  } = $props();

  let showAll = $derived(destinations.length > 1);
</script>

<div class="bs-tabs" role="tablist">
  {#if showAll}
    <button type="button" class="bs-tab" class:bs-tab--active={active === 'all'} onclick={() => onSelect('all')}>
      All Destinations
    </button>
  {/if}
  {#each destinations as d (d.id)}
    <button type="button" class="bs-tab" class:bs-tab--active={active === d.id} onclick={() => onSelect(d.id)}>
      {d.name || 'Destination'}{#if !d.enabled}<span class="bs-tab__off">off</span>{/if}
    </button>
  {/each}
  <button type="button" class="bs-tab bs-tab--add" onclick={onAdd} aria-label="Add destination">+</button>
</div>

<style>
  .bs-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: var(--bs-space--2xs);
    border-bottom: var(--bs-border--1) solid var(--bs-color-border);
    padding-bottom: var(--bs-space--xs);
  }

  .bs-tab {
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid transparent;
    border-radius: var(--bs-radius--md) var(--bs-radius--md) 0 0;
    background: none;
    color: var(--bs-color-text--muted);
    font: inherit;
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    cursor: pointer;
  }

  .bs-tab:hover {
    color: var(--bs-color-text);
  }

  .bs-tab--active {
    background: var(--bs-color-surface--raised);
    border-color: var(--bs-color-border);
    color: var(--bs-color-text);
  }

  .bs-tab--add {
    color: var(--bs-color-primary);
    font-size: var(--bs-text--lg);
    line-height: 1;
  }

  .bs-tab__off {
    margin-left: var(--bs-space--2xs);
    font-size: var(--bs-text--xs);
    color: var(--bs-color-text--subtle);
  }
</style>
