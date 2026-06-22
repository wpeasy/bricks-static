<script lang="ts">
  import type { DestinationDisplay } from '../shared/types';
  import { canAddDestination } from '../shared/capabilities.svelte';
  import { __ } from '../shared/i18n';

  let {
    destinations,
    active,
    onSelect,
    onAdd,
    onRename,
  }: {
    destinations: DestinationDisplay[];
    active: string;
    onSelect: (tab: string) => void;
    onAdd: () => void;
    onRename: (id: string, name: string) => void;
  } = $props();

  let showAll = $derived(destinations.length > 1);
  // Multiple destinations are a Pro capability; the cap is 1 in Free.
  let canAdd = $derived(canAddDestination(destinations.length));

  let editingId = $state('');
  let editValue = $state('');

  function startEdit(d: DestinationDisplay): void {
    editingId = d.id;
    editValue = d.name;
  }

  function commit(): void {
    const name = editValue.trim();
    if (editingId && name) {
      onRename(editingId, name);
    }
    editingId = '';
  }

  function onKey(e: KeyboardEvent): void {
    if (e.key === 'Enter') commit();
    else if (e.key === 'Escape') editingId = '';
  }
</script>

<div class="bs-tabs" role="tablist">
  {#if showAll}
    <button type="button" class="bs-tab" class:bs-tab--active={active === 'all'} onclick={() => onSelect('all')}>
      {__('allDestinations')}
    </button>
  {/if}
  {#each destinations as d (d.id)}
    {#if editingId === d.id}
      <input
        class="bs-tab bs-tab--edit"
        bind:value={editValue}
        onblur={commit}
        onkeydown={onKey}
        aria-label={__('destNameAria')}
      />
    {:else}
      <button
        type="button"
        class="bs-tab"
        class:bs-tab--active={active === d.id}
        onclick={() => onSelect(d.id)}
        ondblclick={() => startEdit(d)}
        title={__('dblClickRename')}
      >
        {d.name || __('destinationDefault')}{#if !d.enabled}<span class="bs-tab__off">{__('tabOff')}</span>{/if}
      </button>
    {/if}
  {/each}
  <button
    type="button"
    class="bs-tab bs-tab--add"
    onclick={() => canAdd && onAdd()}
    disabled={!canAdd}
    data-balloon={canAdd ? null : __('multiDestReqPro')}
    data-balloon-pos="down"
    aria-label={__('addDestAria')}
  >+</button>
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
    flex: 0 0 auto;
    text-align: center;
    padding: var(--bs-space--xs) var(--bs-space--md);
    border: var(--bs-border--1) solid transparent;
    border-radius: var(--bs-radius--md) var(--bs-radius--md) 0 0;
    background: none;
    color: var(--bs-color-text--muted);
    font: inherit;
    font-size: var(--bs-text--sm);
    font-weight: var(--bs-weight--medium);
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .bs-tab:hover {
    color: var(--bs-color-text);
  }

  .bs-tab--active {
    background: var(--bs-color-surface--raised);
    border-color: var(--bs-color-border);
    color: var(--bs-color-text);
  }

  .bs-tab--edit {
    background: var(--bs-color-surface);
    border-color: var(--bs-color-primary);
    color: var(--bs-color-text);
  }

  .bs-tab--add {
    flex: 0 0 auto;
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
